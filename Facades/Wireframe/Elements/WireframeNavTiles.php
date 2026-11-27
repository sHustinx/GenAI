<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/**
 * Previews NavTiles as the tile groups Core generates from the page menu (see Core NavTiles): one group
 * for the root's children, then one per page with children. Only pages the user may open are included;
 * tiles of same-app pages link to their wireframe.
 */
class WireframeNavTiles extends WireframeBasicElement
{
    private const MAX_DEPTH = 3;
    private const MAX_TILES = 200;

    private int $tileCount = 0;

    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::projectDetails()
     */
    public function projectDetails(array $config, callable $resolvePageUrl, callable $registerDialog): array
    {
        $depth = is_numeric($config['depth'] ?? null) ? max(1, min(self::MAX_DEPTH, (int) $config['depth'])) : 2;
        $menu = $this->getFactory()->resolveMenu(self::configuredText($config['root_page_alias'] ?? null), $depth);
        if ($menu === null) {
            return [];
        }
        $options = [
            'depth' => $depth,
            'overview' => in_array($config['show_overview_group'] ?? false, [true, 1, '1', 'true'], true),
            'parentPath' => in_array($config['show_parent_path'] ?? false, [true, 1, '1', 'true'], true),
        ];
        $groups = [];
        foreach ($menu as $root) {
            if ($root['children'] !== []) {
                $this->projectGroup($root['children'], $root['label'], 0, $options, $groups);
            }
        }
        return ['tileGroups' => $groups];
    }

    /**
     * Mirrors NavTiles::createTileGroupFromNodes(), including hidden overview tiles and empty groups.
     *
     * @param array $nodes
     * @param string $caption
     * @param int $level
     * @param array $options
     * @param array $groups
     * @return void
     */
    private function projectGroup(array $nodes, string $caption, int $level, array $options, array &$groups): void
    {
        $index = count($groups);
        $groups[] = ['caption' => $caption, 'tiles' => []];
        foreach ($nodes as $node) {
            $hidden = $options['depth'] !== 1 && $level === 0 && ! $options['overview'] && $node['children'] !== [];
            if (! $hidden && $this->tileCount++ < self::MAX_TILES) {
                $groups[$index]['tiles'][] = ['title' => $node['label'], 'subtitle' => $node['description'], 'url' => $node['url']];
            }
            if ($node['children'] !== []) {
                $childCaption = $options['parentPath'] ? $caption . ' > ' . $node['label'] : $node['label'];
                $this->projectGroup($node['children'], $childCaption, $level + 1, $options, $groups);
            }
        }
        if ($groups[$index]['tiles'] === []) {
            array_splice($groups, $index, 1);
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        $groups = $this->getNode()['tileGroups'] ?? null;
        if ($groups === null) {
            return '<p><small>Tiles are generated from the page menu at runtime.</small></p>';
        }
        if ($groups === []) {
            return '<p><small>No accessible menu pages below this page.</small></p>';
        }
        $html = '';
        foreach ($groups as $group) {
            $html .= '<div class="group-label">' . self::escape($group['caption']) . '</div><div class="tiles">';
            foreach ($group['tiles'] as $tile) {
                $html .= $this->buildHtmlTile($tile);
            }
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * A tile with title and subtitle; it links to the target's wireframe only if that page is previewable.
     *
     * @param array $tile
     * @return string
     */
    protected function buildHtmlTile(array $tile): string
    {
        $inner = '<strong>' . self::escape($tile['title']) . '</strong>'
            . ($tile['subtitle'] === null ? '' : '<small>' . self::escape($tile['subtitle']) . '</small>');
        if ($tile['url'] !== null) {
            return '<a class="tile" href="' . self::escape($tile['url']) . '" title="Open page preview">' . $inner . '</a>';
        }
        return '<span class="tile inert" title="Page outside this app\'s preview">' . $inner . '</span>';
    }
}
