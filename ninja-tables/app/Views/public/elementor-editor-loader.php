<?php if (!defined('ABSPATH')) exit; ?>
<script type="text/javascript">
    /* Ninja Tables: load a table engine in the Elementor editor only when a widget uses it. */
    jQuery(window).on('elementor/frontend/init', function () {
        if (!window.elementorFrontend || !elementorFrontend.hooks || typeof elementorFrontend.hooks.addAction !== 'function') {
            return;
        }

        var loaded = {};

        // Copy one parked tag into the page; resolves once a script file has run.
        function addTag(tag) {
            if (tag.tagName !== 'SCRIPT') {
                document.head.appendChild(document.importNode(tag, true));
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                var script = document.createElement('script');
                Array.prototype.forEach.call(tag.attributes, function (attr) {
                    script.setAttribute(attr.name, attr.value);
                });
                script.text = tag.text;
                if (script.src) {
                    script.onload = script.onerror = resolve;
                }
                document.body.appendChild(script);
                if (!script.src) {
                    resolve();
                }
            });
        }

        // Each group loads once; its tags are added in order, as WordPress printed them.
        function loadGroup(name) {
            if (!loaded[name]) {
                // The group's tags are stored as a JSON string; a <template> parses them without running anything.
                var data = document.querySelector('script.ninja-tables-editor-assets[data-group="' + name + '"]');
                var template = document.createElement('template');
                template.innerHTML = data ? JSON.parse(data.textContent) : '';
                var tags = Array.prototype.slice.call(template.content.children);
                loaded[name] = tags.reduce(function (ready, tag) {
                    return ready.then(function () {
                        return addTag(tag);
                    });
                }, Promise.resolve());
            }
            return loaded[name];
        }

        // Elementor runs these hooks without a try/catch: an error here must not stop other widgets.
        elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
            try {
                loadFor($scope);
            } catch (e) {
                console.error('Ninja Tables:', e);
            }
        });

        function loadFor($scope) {
            var hasFooTable = $scope.find('table.ninja_footable').length > 0;
            var hasDataTable = $scope.find('table.ninja_datatable').length > 0;
            if (!hasFooTable && !hasDataTable) {
                return;
            }

            var ready = loadGroup('common');
            if (hasFooTable) {
                ready = ready.then(function () {
                    return loadGroup('footable');
                });
            }
            if (hasDataTable) {
                ready = ready.then(function () {
                    return loadGroup('datatables');
                });
            }

            ready.then(function () {
                if (hasFooTable && window.ninjaTableApp) {
                    window.ninjaTableApp.initTablesIn($scope);
                }
                if (hasDataTable && window.ninjaDataTableApp) {
                    window.ninjaDataTableApp.initTables($scope);
                }
            }).catch(function (e) {
                console.error('Ninja Tables:', e);
            });
        }
    });
</script>
