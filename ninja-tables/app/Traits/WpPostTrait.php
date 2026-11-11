<?php

namespace NinjaTables\App\Traits;

use NinjaTables\Framework\Support\Arr;

trait WpPostTrait
{
    public function wpInsertPost($postTitle)
    {
        $my_post = [
            'post_title'  => $postTitle,
            'post_type'   => 'ninja-table',
            'post_status' => 'publish'
        ];

        return wp_insert_post($my_post);
    }

    public function updatePostMeta($tableId, array $data)
    {
        update_post_meta($tableId, '_ninja_tables_data_provider', 'drag_and_drop');
        update_post_meta($tableId, '_ninja_table_builder_table_html', $data['table_html']);
        update_post_meta($tableId, '_ninja_table_builder_table_settings', $this->keepStoredCustomJs($tableId, $data['table_settings']));
        update_post_meta($tableId, '_ninja_table_builder_table_responsive', $data['table_responsive']);
        update_post_meta($tableId, '_ninja_table_builder_table_data', $data['table_data']);
    }

    /**
     * Custom JS prints raw to every visitor, so without unfiltered_html the builder settings
     * keep the table's stored JS. Arr::get() also reads a literal "custom_js.value" key.
     */
    protected function keepStoredCustomJs($tableId, $settings)
    {
        if (!is_array($settings) || ninjaTablesCanUnfilteredHTML()) {
            return $settings;
        }

        $storedJs = Arr::get(get_post_meta($tableId, '_ninja_table_builder_table_settings', true), 'custom_js.value', '');

        unset($settings['custom_js.value']);

        $customJs          = is_array(Arr::get($settings, 'custom_js')) ? $settings['custom_js'] : [];
        $customJs['value'] = is_string($storedJs) ? wp_slash($storedJs) : '';

        $settings['custom_js'] = $customJs;

        return $settings;
    }

    public function wpUpdatePost($table_id, $postTitle)
    {
        $my_post = [
            'ID'          => $table_id,
            'post_title'  => $postTitle,
            'post_type'   => 'ninja-table',
            'post_status' => 'publish'
        ];

        return wp_update_post($my_post);
    }
}
