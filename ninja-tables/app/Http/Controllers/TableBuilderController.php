<?php

namespace NinjaTables\App\Http\Controllers;

use NinjaTables\App\Modules\TableBuilder\InitConfig;
use NinjaTables\App\Traits\WpPostTrait;
use NinjaTables\Framework\Http\Request\Request;
use NinjaTables\Framework\Http\Controller;
use NinjaTables\Framework\Support\Arr;
use NinjaTables\Framework\Support\Sanitizer;
use NinjaTables\App\Modules\TableBuilder\DynamicConfig;
use NinjaTables\App\Modules\TableBuilder\ReadyMadeTable;

class TableBuilderController extends Controller
{
    use WpPostTrait;

    public function index(Request $request)
    {
        $initConfig = (new InitConfig())->getAllInitialData();

        return $this->sendSuccess($initConfig, 200);
    }

    public function store(Request $request)
    {
        $data    = json_decode(Arr::get($request->all(), 'data'), true);
        $table_type = Sanitizer::sanitizeTextField(Arr::get($data, 'table_data.table_type'));
        $table_name = Sanitizer::sanitizeTextField(Arr::get($data, 'table_data.table_name'));

        if (isset($table_type) && $table_type !== '') {
            return $this->generateByTemplateConfig($table_type, $table_name); // for ready-made table
        }


        $initConfig            = new InitConfig();
        $table_id              = $this->wpInsertPost($table_name);
        if (!$table_id || is_wp_error($table_id)) {
            return $this->sendError(['data' => ['message' => __('Could not create the table.', 'ninja-tables')]], 500);
        }
        if (!$table_name) {
            /* translators: %d: table ID */
            $table_name = sprintf(__('Table #%d', 'ninja-tables'), $table_id);
            $updated = $this->wpUpdatePost($table_id, $table_name);
            if (!$updated || is_wp_error($updated)) {
                return $this->sendError(['data' => ['message' => __('Could not name the table.', 'ninja-tables')]], 500);
            }
            $data['table_data']['table_name'] = $table_name;
        }
        $data                  = sanitize_post_field('data', $data, $table_id, 'db');
        $table_data            = $data['table_data'];
        $table_data['headers'] = $initConfig->makeTableHeader($table_data);
        $table_data['data']    = $initConfig->makeTableRow($table_data);

        $meta_data = [
            'table_name'       => $table_name,
            'table_settings'   => $initConfig->settingConfig(),
            'table_responsive' => $initConfig->responsiveConfig(),
            'table_data'       => $table_data,
            'table_html'       => null
        ];

        $this->updatePostMeta($table_id, $meta_data);

        return $this->sendSuccess([
            'data' => [
                'id' => $table_id
            ]
        ], 200);
    }

    /**
     * Set style.inlineLayout on every Text Icon element in the builder's row data.
     */
    private function withInlineTextIcons(array $rows)
    {
        foreach ($rows as &$row) {
            if (empty($row['rows']) || !is_array($row['rows'])) {
                continue;
            }
            foreach ($row['rows'] as &$cell) {
                if (empty($cell['columns']) || !is_array($cell['columns'])) {
                    continue;
                }
                foreach ($cell['columns'] as &$element) {
                    if (isset($element['data']['type']) && $element['data']['type'] === 'text_icon') {
                        $element['data']['style']['inlineLayout'] = true;
                    }
                }
                unset($element);
            }
            unset($cell);
        }
        unset($row);

        return $rows;
    }

    public function generateByTemplateConfig($table_type, $table_name)
    {
        $table            = (new ReadyMadeTable())->tableByType($table_type);
        $table_settings   = $table['table_settings'];
        $table_responsive = $table['table_responsive'];
        $table_data       = $table['table_data'];

        if ( ! empty($table_name)) {
            $table_data['table_name'] = $table_name;
        }

        // A table made from a template is new, so its Text Icon elements get the layout that
        // keeps text beside the icon. Tables that already exist are never touched.
        if (isset($table_data['data']) && is_array($table_data['data'])) {
            $table_data['data'] = $this->withInlineTextIcons($table_data['data']);
        }

        $table_id = $this->wpInsertPost($table_data['table_name']);

        $data = [
            'table_id'         => $table_id,
            'table_settings'   => $table_settings,
            'table_responsive' => $table_responsive,
            'table_data'       => $table_data,
            'table_html'       => null
        ];

        $this->updatePostMeta($table_id, $data);

        return $this->sendSuccess([
            'data' => [
                'id' => $table_id
            ]
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $initConfig        = new InitConfig();
        $table_id          = intval($id);
        $table_settings    = get_post_meta($table_id, '_ninja_table_builder_table_settings', true);
        $table_responsive  = get_post_meta($table_id, '_ninja_table_builder_table_responsive', true);
        $table_data        = get_post_meta($table_id, '_ninja_table_builder_table_data', true);
        $components        = $initConfig->componentConfig();
        $ready_made_tables = $initConfig->templateConfig();
        $table_data_info   = DynamicConfig::getTableDataInfo($table_data['data'], $initConfig->tableColumnStyling(),
            $initConfig->tableRawStyling());

        // The post title is the table's name everywhere else (list, block, REST), so the
        // builder starts from it too. Otherwise a rename made outside the builder came back
        // as the old name on the next Save.
        $post_title = get_the_title($table_id);
        $table_name = $post_title !== '' ? $post_title : $table_data['table_name'];

        // Tables created from a template have data but no rendered HTML until the builder
        // saves once; the builder uses this to save silently after its first render.
        $needs_html = trim((string) get_post_meta($table_id, '_ninja_table_builder_table_html', true)) === '';

        return $this->sendSuccess([
            'data' => [
                'settings'          => DynamicConfig::getSetting($table_settings, $initConfig->settingConfig()),
                'responsive'        => DynamicConfig::getResponsive($table_responsive, $initConfig->responsiveConfig()),
                'components'        => $components,
                'ready_made_tables' => $ready_made_tables,
                'needs_html'        => $needs_html,
                'table_data'        => [
                    'id'         => $table_id,
                    'table_name' => html_entity_decode($table_name, ENT_QUOTES, 'UTF-8'),
                    'data'       => $table_data_info,
                    'headers'    => $table_data['headers'],
                    'table'      => array_replace_recursive($initConfig->getOtherTableConfig(), $table_data['table']),
                    'preview_url' => site_url('?ninjatable_builder_preview=' . $table_id)
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $table_id   = intval($id);

        if (!$table_id || get_post_type($table_id) !== 'ninja-table') {
            return $this->sendError([
                'data' => ['message' => __('Invalid table.', 'ninja-tables')]
            ], 423);
        }

        $table_html = ninjaTablesEscapeScript(Arr::get($request->all(), 'table_html'));
        $json       = ninjaTablesEscapeScript(Arr::get($request->all(), 'data'));
        $data = json_decode($json, true);
        if ($data === null) {
            $data = json_decode(htmlspecialchars_decode($json), true);
        }

        if ( ! ninjaTablesCanUnfilteredHTML()) {
            ninja_tables_allowed_css_properties();
            $table_html = $this->convertRGBtoHex($table_html);
            $table_html = wp_kses($table_html, ninja_tables_allowed_html_tags());
        }

        $table_name            = Arr::get($data, 'table_data.table_name');
        $table_settings        = Arr::get($data, 'settings');
        $table_responsive      = Arr::get($data, 'responsive');
        $table_data            = Arr::get($data, 'table_data');
        $table_data['headers'] = Arr::get($data, 'table_data.headers');


        $this->wpUpdatePost($table_id, $table_name);

        $data = [
            'table_name'       => $table_name,
            'table_settings'   => $table_settings,
            'table_responsive' => $table_responsive,
            'table_data'       => $table_data,
            'table_html'       => $table_html
        ];

        $this->updatePostMeta($table_id, $data);

        return $this->sendSuccess([
            'data' => [
                'id' => $table_id
            ]
        ], 200);
    }

    public function convertRGBtoHex($tableHtml)
    {
        return preg_replace_callback('/rgb\(\d{1,3},\s*\d{1,3},\s*\d{1,3}\)/', function ($matches) {
            $rgbArray = explode(",", $matches[0]);
            $red      = intval(trim($rgbArray[0], "rgb()"));
            $green    = intval(trim($rgbArray[1]));
            $blue     = intval(trim($rgbArray[2], " )"));

            // Convert the RGB values to hex format
            $hexCode = "#" . str_pad(dechex($red), 2, "0", STR_PAD_LEFT) . str_pad(dechex($green), 2, "0",
                    STR_PAD_LEFT) . str_pad(dechex($blue), 2, "0", STR_PAD_LEFT);

            // Return the hex code
            return $hexCode;
        }, $tableHtml);
    }
}
