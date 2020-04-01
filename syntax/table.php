<?php
/**
 * DokuWiki Plugin groupmatrix (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Anna Dabrowska <dokuwiki@cosmocode.de>
 */

class syntax_plugin_groupmatrix_table extends DokuWiki_Syntax_Plugin
{
    const MARK = '✓';

    /**
     * @return string Syntax mode type
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType()
    {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort()
    {
        return 100;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('---+ *groupmatrix *-+\n.*?\n----+', $mode, 'plugin_groupmatrix_table');
    }

    /**
     * Handle matches of the groupmatrix syntax
     *
     * @param string $match The match of the syntax
     * @param int $state The state of the handler
     * @param int $pos The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $data = [
            'headers' => [],
            'rows' => [],
        ];

        $lines = explode("\n", $match);

        // get rid of opening and closing syntax lines
        array_shift($lines);
        array_pop($lines);

        $cfg = [];
        foreach ($lines as $line) {
            list($key, $value) = explode(':', $line);
            $cfg[trim($key)] = trim($value);
        }

        if (empty($cfg['groups'])) {
            msg('Missing groups configuration', -1);
            return $data;
        }

        $data['attributes'] = $this->trimexplode(',', $cfg['attributes']);
        $data['groups'] = $this->trimexplode(',', $cfg['groups']);
        $titles = $this->trimexplode(',', $cfg['titles']);
        if (empty($data['attributes'])) $data['attributes'] = ['user'];

        $groupHeaders = $titles ? array_replace($data['groups'], $titles) : $data['groups'];
        $data['headers'] = array_merge($data['attributes'], $groupHeaders);

        return $data;
    }

    /**
     * Render xhtml output
     *
     * @param string $mode Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     *
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== 'xhtml') {
            return false;
        }

        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $groups = $data['groups'];
        $attributes = $data['attributes'];

        $rows = [];
        foreach ($groups as $group) {
            $users = $auth->retrieveUsers(0, -1, ['grps' => $group]);
            foreach ($users as $user => $userinfo) {
                // not all backends return the user in the info array, fix that here
                $userinfo['user'] = $user;
                if (!isset($rows[$user])) {
                    // prepare row of attributes + groups
                    $rows[$user] = array_merge(
                        array_merge(
                            // ensure all atributes are set, even when missing in $user
                            array_fill_keys($attributes, ''),
                            // extract the wanted attributes from $user
                            array_intersect_key($userinfo, array_flip($attributes))
                        ),
                        // add all groups to the row
                        array_fill_keys($groups, '')
                    );
                }
                // add group membership
                $rows[$user][$group] = self::MARK;
            }
        }

        // sort by user name
        ksort($rows);

        $renderer->doc .= $this->renderTable($data['headers'], $rows);
        return true;
    }

    /**
     * Return table HTML. The first column is the user name, the rest comes from config.
     *
     * @param array $headers
     * @param array $rows
     * @param string $className
     * @return string
     */
    protected function renderTable($headers, $rows, $className = '')
    {
        $html = '<table class="inline ' . $className . '">';

        $html .= '<thead>';
        $html .= '<tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . $header . '</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';

        $html .= '<tbody>';
        if ($rows) {
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= $this->renderTableCells($row);
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Explode a string and trim the resulting array items
     *
     * @param string $delimiter
     * @param string $string
     * @return array
     */
    protected function trimexplode($delimiter, $string)
    {
        $arr = [];
        foreach (explode($delimiter, $string) as $value) {
            if (!$value) {
                continue;
            }
            $arr[] = trim($value);
        }
        return $arr;
    }

    /**
     * Wrap all items in <td> tags, flattening any contained arrays.
     *
     * @param array $row
     * @param string $html
     * @return string
     */
    protected function renderTableCells($row, $html = '')
    {
        foreach ($row as $item) {
            if (!is_array($item)) {
                if ($item === self::MARK) {
                    $html .= '<td class="centeralign">';
                } else {
                    $html .= '<td>';
                }
                $html .= hsc($item) . '</td>';
            } else {
                return $this->renderTableCells($item, $html);
            }
        }
        return $html;
    }
}

