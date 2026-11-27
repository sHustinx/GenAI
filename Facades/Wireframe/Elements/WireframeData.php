<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/**
 * Lays out data widgets (DataTable, DataTree, DataList, ...) like the real page: a filter bar, the
 * button toolbar, and a table with column headers and empty skeleton rows. No rows are ever read.
 * Each part is its own hook, so subclasses can change one of them.
 */
class WireframeData extends WireframeButtonContainer
{
    protected const SHOW_OBJECT = true;
    protected const CHILD_TYPES = WireframeBasicElement::CHILD_TYPES + [
        'columns' => 'DataColumn',
        'column_groups' => 'DataColumnGroup',
        'filters' => 'Filter',
    ] + parent::CHILD_TYPES;

    /** Groups drawn as the table rather than as child boxes. */
    protected const TABLE_GROUPS = ['columns', 'column_groups'];
    protected const SKELETON_ROWS = 3;

    /**
     * The inert "Read data" control goes into the filter bar; without filters it stands alone.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return empty($this->getNode()['children']['filters']) ? '<div class="toolbar-row">' . $this->buildHtmlReadButton() . '</div>' : '';
    }

    /**
     * Filters first, then toolbars and buttons, then the table, then any remaining groups.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildHtmlChildren()
     */
    protected function buildHtmlChildren(): string
    {
        $children = $this->getNode()['children'];
        $html = isset($children['filters']) ? $this->buildHtmlFilterBar($children['filters']) : '';
        $rest = array_diff_key($children, array_flip([...static::TABLE_GROUPS, 'filters']));
        foreach ($rest as $group => $items) {
            if (in_array($group, static::BUTTON_GROUPS, true) || in_array($group, ['toolbar', 'toolbars'], true)) {
                $html .= $this->buildHtmlChildGroup($group, $items);
                unset($rest[$group]);
            }
        }
        $columns = [...($children['columns'] ?? []), ...($children['column_groups'] ?? [])];
        if ($columns !== []) {
            $html .= $this->buildHtmlTable($columns);
        }
        foreach ($rest as $group => $items) {
            if (! in_array($group, static::UNLABELED_GROUPS, true)) {
                $html .= '<div class="group-label">' . self::escape(ucfirst(str_replace('_', ' ', $group))) . '</div>';
            }
            $html .= $this->buildHtmlChildGroup($group, $items);
        }
        return $html;
    }

    /**
     * Filters as labeled input boxes in a wrapping bar, followed by the inert "Read data" control.
     *
     * @param array $filters
     * @return string
     */
    protected function buildHtmlFilterBar(array $filters): string
    {
        $html = '<div class="filter-bar">';
        foreach ($filters as $filter) {
            $html .= '<div class="filter-item"><span>' . self::escape($filter['caption'] ?? $filter['type']) . '</span>'
                . '<span class="field input" aria-hidden="true"></span></div>';
        }
        return $html . $this->buildHtmlReadButton() . '</div>';
    }

    /**
     * @return string
     */
    protected function buildHtmlReadButton(): string
    {
        return '<span class="button inert" aria-disabled="true" title="No data is read in preview">Read data</span>';
    }

    /**
     * A table with one header cell per column; column groups get a spanning header above their columns.
     *
     * @param array[] $columns Projected columns and column groups in configured order.
     * @return string
     */
    protected function buildHtmlTable(array $columns): string
    {
        $hasGroups = false;
        $top = '';
        $bottom = '';
        $leafCount = 0;
        foreach ($columns as $column) {
            $members = $column['children']['columns'] ?? [];
            if ($members === []) {
                // Spans both header rows if there are groups; the marker is removed otherwise.
                $top .= '<th rowspan="2">' . $this->buildHtmlColumnHeader($column) . '</th>';
                $leafCount++;
                continue;
            }
            $hasGroups = true;
            $top .= '<th class="column-group" colspan="' . count($members) . '">' . $this->buildHtmlColumnHeader($column) . '</th>';
            foreach ($members as $member) {
                $bottom .= '<th>' . $this->buildHtmlColumnHeader($member) . '</th>';
                $leafCount++;
            }
        }
        if (! $hasGroups) {
            $top = str_replace(' rowspan="2"', '', $top);
        }
        $head = '<tr>' . $top . '</tr>' . ($hasGroups ? '<tr>' . $bottom . '</tr>' : '');
        $row = '<tr>' . str_repeat('<td><span class="skeleton"></span></td>', $leafCount) . '</tr>';
        return '<div class="table-wrap"><table class="data-table"><thead>' . $head . '</thead><tbody aria-hidden="true">'
            . str_repeat($row, static::SKELETON_ROWS) . '</tbody></table></div>';
    }

    /**
     * Column caption; a column bound to an action keeps its button so its preview stays reachable.
     *
     * @param array $column
     * @return string
     */
    protected function buildHtmlColumnHeader(array $column): string
    {
        if (isset($column['action'])) {
            return $this->getFactory()->buildHtml($column);
        }
        return self::escape($column['caption'] ?? $column['type']) . $this->buildHtmlOmitted($column);
    }
}
