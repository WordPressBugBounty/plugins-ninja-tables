<?php

namespace NinjaTables\App\Hooks\Handlers;

use NinjaTables\App\App;
use NinjaTables\App\Services\Vite;
use NinjaTables\App\Modules\DataProviders\NinjaFooTable;

class PreviewHandler
{
    public function register()
    {
        add_action('wp_loaded', [$this, 'defaultTable']);
        add_action('wp_loaded', [$this, 'dragAndDropTable']);
    }

    public function defaultTable()
    {
        $tableId = null;

        if (isset($_GET['ninjatable_preview'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $tableId = intval($_GET['ninjatable_preview']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ($tableId) {
            if (ninja_table_admin_role()) {
                do_action('ninja_tables_will_render_table', $tableId);

                Vite::enqueueStyle('ninja-tables-preview', 'css/ninja-tables-preview.css');

                NinjaFooTable::enqueuePublicCss();
                $table = get_post($tableId);

                if ($table) {
                    $app = App::getInstance();
                    $app->view->render('/admin/preview/default-table', [
                        'table_id' => $tableId
                    ]);
                    exit();
                }
            }
        }
    }

    public function dragAndDropTable()
    {
        if (isset($_GET['ninjatable_builder_preview']) && intval(wp_unslash($_GET['ninjatable_builder_preview']))) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (ninja_table_admin_role()) {
                $tableId = intval(wp_unslash($_GET['ninjatable_builder_preview'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

                do_action('ninja_tables_will_render_table', $tableId);

                Vite::enqueueStyle('ninja-tables-preview', 'css/ninja-tables-preview.css');

                $table   = get_post($tableId);

                if ($table) {
                    $app = App::getInstance();
                    $app->view->render('/admin/preview/drag-and-drop', [
                        'table_id' => $tableId
                    ]);
                    exit();
                }
            }
        }
    }
}
