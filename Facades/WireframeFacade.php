<?php
namespace axenox\GenAI\Facades;

use axenox\GenAI\Common\UiWireframeProjector;
use axenox\GenAI\Facades\Wireframe\WireframeDialogPages;
use axenox\GenAI\Facades\Wireframe\WireframeElementFactory;
use axenox\GenAI\Facades\Wireframe\WireframeWidgetSchema;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Security\Authorization\UiPageAuthorizationPoint;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Serves read-only app, page, and dialog previews from saved UXON and model metadata.
 * The original page source is available separately to authorized Superusers.
 */
class WireframeFacade extends AbstractHttpFacade
{
    private const ROUTE = 'api/wireframe';
    private const SELECTOR = '[a-zA-Z0-9_][a-zA-Z0-9_.-]*';

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return self::ROUTE;
    }

    /**
     * Match the preview route under the installation path, not inside a query value.
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRoutePatterns()
     */
    public function getUrlRoutePatterns(): array
    {
        return ['~^[^?]*/api/wireframe(?:[/?]|$)~'];
    }

    /**
     * Authorize the source page before rendering its wireframe, a dialog subtree, or its original UXON.
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request): ResponseInterface
    {
        // The preview is strictly read-only, so any other method is rejected before doing any work.
        if ($request->getMethod() !== 'GET') {
            return $this->htmlResponse(405, 'Only GET is supported.', ['Allow' => 'GET']);
        }

        // Page permissions depend on the user, so anonymous visitors never get past this point.
        $workbench = $this->getWorkbench();
        $security = $workbench->getSecurity();
        $token = $security->getAuthenticatedToken();
        if ($token->isAnonymous()) {
            return $this->htmlResponse(403, 'Sign in to ExFace before opening the wireframe.');
        }

        // Route: {base}/{app}/{page?}. Both segments must be plain aliases, so no path tricks reach the model loaders.
        $basePath = parse_url($this->buildUrlToFacade(), PHP_URL_PATH);
        $route = '~^' . preg_quote($basePath, '~') . '/(' . self::SELECTOR . ')(?:/(' . self::SELECTOR . '))?/?$~D';
        if (! preg_match($route, $request->getUri()->getPath(), $matches)) {
            return $this->htmlResponse(404, 'Use /api/wireframe/{app}/{page?} to open an app.');
        }
        // Validate query parameters against an allow-list before they influence anything else.
        $query = $request->getQueryParams();
        $view = $query['view'] ?? 'wireframe';
        if (! in_array($view, ['wireframe', 'uxon'], true)) {
            return $this->htmlResponse(400, 'Invalid wireframe view.');
        }
        // A dialog link lists the buttons followed from this page (see WireframeDialogPages), never selectors or UXON.
        $dialogSteps = WireframeDialogPages::parse($query['dialog'] ?? null);
        if ($dialogSteps === null || ($dialogSteps !== [] && $view !== 'wireframe')) {
            return $this->htmlResponse(400, 'Invalid dialog preview link.');
        }
        try {
            $app = $workbench->getApp($matches[1]);
        } catch (AppNotFoundError $e) {
            return $this->htmlResponse(404, 'App not found.');
        }
        $appAlias = $app->getAliasWithNamespace();
        $appUrl = $basePath . '/' . rawurlencode($appAlias);
        // The menu shows only pages of this app the user may open; it also supplies the default page.
        $tree = UiPageTreeFactory::createFromRoot($workbench);
        $pageAuthorization = $security->getAuthorizationPoint(UiPageAuthorizationPoint::class);
        $menu = $this->projectMenu($tree->getRootNodes(), $pageAuthorization, $appAlias, $appUrl);
        $pageSelector = $matches[2] ?? $menu[0]['alias'] ?? null;
        if ($pageSelector === null) {
            return $this->htmlResponse(404, 'No available menu pages were found for this app.');
        }
        // Missing, denied, and foreign-app pages all give the same 404, so their existence is not revealed.
        $page = $this->findPermittedPage($pageSelector, $appAlias);
        if ($page === null) {
            return $this->htmlResponse(404, 'Page not available in this app.');
        }

        // Raw UXON may contain internal details (filters, formulas, IDs), so it is enforced server-side, not just hidden in the UI.
        $canViewUxon = $security->getUser($token)->hasRole(new UserRoleSelector($workbench, 'exface.Core.SUPERUSER'));
        if ($view === 'uxon' && ! $canViewUxon) {
            return $this->htmlResponse(403, 'Original UXON is available only to Superusers.');
        }

        $pageAlias = $page->getAliasWithNamespace();
        $pageUrl = $appUrl . '/' . rawurlencode($pageAlias);
        $pageName = $page->getName();
        $appName = $app->getName();
        // Only a JSON object counts as widget UXON; other page contents (HTML, lists) are not previewed.
        $contents = $page->getContents();
        $config = is_string($contents) ? json_decode($contents, true) : null;
        $isUxon = is_array($config) && ($config === [] || ! array_is_list($config));
        $canViewUxon = $canViewUxon && $isUxon;
        if ($view === 'uxon' && ! $isUxon) {
            return $this->htmlResponse(404, 'This page does not have saved UXON.');
        }

        // Link to the page in its real facade; the normal UI applies its own permission checks there.
        $normalFacade = $page->getFacade();
        $normalPageUrl = $normalFacade instanceof HtmlPageFacadeInterface
            ? (string) UriResolver::resolve(new Uri($this->buildUrlToSiteRoot()), new Uri($normalFacade->buildUrlToPage($page)))
            : null;

        $wireframe = null;
        $dialog = null;
        $elements = null;
        $availableLinks = [];
        if ($view === 'wireframe' && $isUxon && $config !== []) {
            // Resolve repeated targets once per request, including missing or denied targets.
            $resolvePageUrl = function (string $selector) use ($appAlias, $appUrl, &$availableLinks): ?string {
                if (! preg_match('~^' . self::SELECTOR . '$~D', $selector)) {
                    return null;
                }
                if (! array_key_exists($selector, $availableLinks)) {
                    $target = $this->findPermittedPage($selector, $appAlias);
                    $availableLinks[$selector] = $target === null ? null
                        : $appUrl . '/' . rawurlencode($target->getAliasWithNamespace());
                }
                return $availableLinks[$selector];
            };
            // Metadata lookups for the elements, each cached per request. They read model definitions only,
            // never business rows, and never construct widgets or actions.
            $objects = [];
            $actionDefinitions = [];
            $widgetSchema = new WireframeWidgetSchema($workbench);

            // Meta objects, used for Create/Edit dialogs without saved editor UXON.
            $resolveObject = static function (string $alias) use ($workbench, &$objects): ?MetaObjectInterface {
                if (! array_key_exists($alias, $objects)) {
                    try {
                        $objects[$alias] = MetaObjectFactory::createFromString($workbench, $alias);
                    } catch (MetaObjectNotFoundError $e) {
                        $workbench->getLogger()->logException($e);
                        $objects[$alias] = null;
                    }
                }
                return $objects[$alias];
            };
            // Saved action definitions, so buttons referencing a modeled action can preview its dialog.
            $resolveActionDefinition = function (string $alias) use (&$actionDefinitions): ?array {
                if (! array_key_exists($alias, $actionDefinitions)) {
                    $actionDefinitions[$alias] = $this->loadActionDefinition($alias);
                }
                return $actionDefinitions[$alias];
            };
            // Widget property annotations for child-group discovery; on failure only declared groups are traversed.
            $resolveWidgetProperties = static function (string $widgetClass) use ($workbench, $widgetSchema): array {
                try {
                    return $widgetSchema->getPropertyTypeAnnotations($widgetClass);
                } catch (\Throwable $e) {
                    $workbench->getLogger()->logException($e);
                    return [];
                }
            };
            // Default button captions: Core actions use their translated name, modeled actions their saved name
            // (or, if empty, the name of their Core prototype).
            $resolveActionName = function (string $alias) use ($resolveActionDefinition): ?string {
                return $this->translateCoreActionName($alias)
                    ?? (null === ($definition = $resolveActionDefinition($alias)) ? null
                        : ($definition['name'] ?? $this->translateCoreActionName($definition['prototype'])));
            };
            // NavTiles menu: the same tree Core builds, limited to pages the user may open; a foreign root is refused.
            $resolveMenu = function (?string $rootPageAlias, int $depth) use ($page, $appAlias, $pageAuthorization, $resolvePageUrl): ?array {
                $root = $rootPageAlias === null ? $page : $this->findPermittedPage($rootPageAlias, $appAlias);
                if ($root === null) {
                    return null;
                }
                $tree = UiPageTreeFactory::createFromRootPage($root, $depth);
                return $this->projectMenuTiles($tree->getRootNodes(), $pageAuthorization, $resolvePageUrl);
            };

            $elements = new WireframeElementFactory($resolveObject, $resolveActionDefinition, $resolveWidgetProperties, $resolveActionName, $resolveMenu);
            $projector = new UiWireframeProjector($elements);
            // Project only the requested view; a dialog is reached by following buttons of the page authorized above.
            if ($dialogSteps === []) {
                $wireframe = $projector->project($config, $resolvePageUrl);
            } else {
                $dialogView = $projector->projectDialog($config, $resolvePageUrl, $dialogSteps);
                if ($dialogView === null) {
                    return $this->htmlResponse(404, 'Dialog preview not available on this page.');
                }
                $wireframe = $dialogView['node'];
                $dialog = [
                    'title' => $dialogView['title'],
                    'backUrl' => WireframeDialogPages::buildUrl($pageUrl, array_slice($dialogSteps, 0, -1)),
                ];
            }
            if ($wireframe !== null) {
                WireframeDialogPages::link($wireframe, $pageUrl, $dialogSteps);
            }
        } elseif ($dialogSteps !== []) {
            return $this->htmlResponse(404, 'Dialog preview not available on this page.');
        }
        $source = $view === 'uxon' ? $this->formatUxon($contents) : null;

        // Render the template with the local variables prepared above; the buffer is always released.
        ob_start();
        try {
            require __DIR__ . '/Wireframe/page.php';
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        return $this->htmlResponse(200, $html);
    }

    /**
     * Read only an unambiguous, accessible action definition without constructing an action.
     *
     * @param string $alias
     * @return array{prototype: string, config: array, object: ?string, name: ?string}|null
     */
    private function loadActionDefinition(string $alias): ?array
    {
        if (! preg_match('/^[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+){2,}$/D', $alias)) {
            return null;
        }
        $workbench = $this->getWorkbench();
        $sheet = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.OBJECT_ACTION');
        $sheet->getColumns()->addMultiple(['ACTION_PROTOTYPE', 'CONFIG_UXON', 'OBJECT__ALIAS_WITH_NS', 'NAME']);
        $sheet->getFilters()->addConditionFromString('ALIAS_WITH_NS', $alias, ComparatorDataType::EQUALS);
        try {
            $count = $sheet->dataRead(2);
        } catch (AuthorizationExceptionInterface $e) {
            $workbench->getLogger()->logException($e);
            return null;
        }
        if ($count !== 1) {
            if ($count > 1) {
                $workbench->getLogger()->warning('Ambiguous modeled action alias in wireframe: ' . $alias);
            }
            return null;
        }
        $row = $sheet->getRows()[0];
        return [
            'prototype' => $row['ACTION_PROTOTYPE'],
            'config' => UxonObject::fromAnything($row['CONFIG_UXON'] ?? '{}')->toArray(),
            'object' => $row['OBJECT__ALIAS_WITH_NS'] ?? null,
            'name' => is_string($row['NAME'] ?? null) && trim($row['NAME']) !== '' ? trim($row['NAME']) : null,
        ];
    }

    /**
     * Indent saved UXON for reading. Decoding to objects keeps `{}` and `[]` apart, and values are
     * not normalized (unlike UxonObject::toJson()), so the view shows exactly what is stored.
     *
     * @param string $contents
     * @return string
     */
    private function formatUxon(string $contents): string
    {
        $decoded = json_decode($contents);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $contents;
        }
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Name a Core action the way AbstractAction::getName() does, from its `ACTION.{ALIAS}.NAME` translation.
     * Accepts an alias (`exface.Core.DeleteObject`) or a stored prototype path or class name.
     *
     * @param string $aliasOrPrototype
     * @return string|null
     */
    private function translateCoreActionName(string $aliasOrPrototype): ?string
    {
        $normalized = str_replace('\\', '/', ltrim($aliasOrPrototype, '\\'));
        if (! preg_match('~^exface[./]core[./](?:actions/)?([a-z0-9_]+)(?:\.php)?$~iD', $normalized, $matches)) {
            return null;
        }
        $key = 'ACTION.' . mb_strtoupper($matches[1]) . '.NAME';
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return $translator->hasTranslation($key) ? $translator->translate($key) : null;
    }

    /**
     * Preserve the framework status code without showing exception details in the browser.
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponseFromError()
     */
    protected function createResponseFromError(\Throwable $exception, ServerRequestInterface $request = null): ResponseInterface
    {
        $status = $exception instanceof ExceptionInterface ? $exception->getStatusCode() : 500;
        return $this->htmlResponse($status, $status >= 500 ? 'Unable to build the wireframe.' : 'Wireframe request denied.');
    }

    /**
     * Keep direct requests and preview links app-scoped, without distinguishing denied and missing pages.
     *
     * @param string $selector
     * @param string $appAlias
     * @return UiPageInterface|null
     */
    private function findPermittedPage(string $selector, string $appAlias): ?UiPageInterface
    {
        try {
            $workbench = $this->getWorkbench();
            $page = UiPageFactory::createFromModel($workbench, $selector);
            $workbench->getSecurity()->getAuthorizationPoint(UiPageAuthorizationPoint::class)->authorize($page);
            return $page->hasApp() && strcasecmp($page->getApp()->getAliasWithNamespace(), $appAlias) === 0
                ? $page : null;
        } catch (AuthorizationExceptionInterface | UiPageNotFoundError $e) {
            return null;
        }
    }

    /**
     * Keep permitted menu nodes with their description; only same-app, permitted pages get a preview URL.
     *
     * @param array $nodes
     * @param UiPageAuthorizationPoint $authorization
     * @param callable $resolvePageUrl
     * @return array
     */
    private function projectMenuTiles(array $nodes, UiPageAuthorizationPoint $authorization, callable $resolvePageUrl): array
    {
        $tiles = [];
        foreach ($nodes as $node) {
            try {
                $authorization->authorize($node);
            } catch (AuthorizationExceptionInterface $e) {
                continue;
            }
            $description = $node->getDescription();
            $tiles[] = [
                'label' => $node->getName(),
                'description' => is_string($description) && trim($description) !== '' ? trim($description) : null,
                'url' => $resolvePageUrl($node->getAliasWithNamespace()),
                'children' => $node->hasChildNodes() ? $this->projectMenuTiles($node->getChildNodes(), $authorization, $resolvePageUrl) : [],
            ];
        }
        return $tiles;
    }

    /**
     * Keep permitted app entries while lifting them out of permitted folders owned by other apps.
     *
     * @param array $nodes
     * @param UiPageAuthorizationPoint $authorization
     * @param string $appAlias
     * @param string $appUrl
     * @return array
     */
    private function projectMenu(array $nodes, UiPageAuthorizationPoint $authorization, string $appAlias, string $appUrl): array
    {
        $menu = [];
        foreach ($nodes as $node) {
            try {
                $authorization->authorize($node);
            } catch (AuthorizationExceptionInterface $e) {
                continue;
            }
            $children = $this->projectMenu($node->getChildNodes(), $authorization, $appAlias, $appUrl);
            if ($node->hasApp() && strcasecmp($node->getApp()->getAliasWithNamespace(), $appAlias) === 0) {
                $alias = $node->getAliasWithNamespace();
                $menu[] = [
                    'label' => $node->getName(), 'alias' => $alias,
                    'url' => $appUrl . '/' . rawurlencode($alias), 'children' => $children,
                ];
            } else {
                array_push($menu, ...$children);
            }
        }
        return $menu;
    }

    /**
     * Prevent caching permission-dependent previews and restrict the markup to static HTML and styles.
     *
     * @param int $status
     * @param string $body
     * @param array $headers
     * @return ResponseInterface
     */
    private function htmlResponse(int $status, string $body, array $headers = []): ResponseInterface
    {
        return new Response($status, $headers + [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
        ] + $this->buildHeadersCommon(), $body);
    }
}
