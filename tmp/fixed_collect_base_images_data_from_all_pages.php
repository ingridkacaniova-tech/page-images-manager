<?php
/**
 * FIXED VERSION - TRUE ORPHANS PATCH (base_filename aware)
 */
public function collect_base_images_data_from_all_pages() {
    PIM_Debug_Logger::log_session_start('collect_base_images_data_from_all_pages');

    error_log("\n🔄🔄🔄 === COLLECT BASE IMAGES DATA FROM ALL PAGES START === 🔄🔄🔄");

    check_ajax_referer('page_images_manager', 'nonce');

    $start_time = microtime(true);

    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ]);

    error_log("📊 Found " . count($pages) . " published pages to scan");

    $total_images_processed = 0;
    $total_uses_found       = 0;
    $all_scan_data          = array();
    $global_duplicates      = array();
    $global_debug_info      = array();
    $total_count            = 0;

    foreach ($pages as $page) {
        error_log("\n📄 === Scanning Page: {$page->post_title} (ID: {$page->ID}) ===");

        $extractor = new PIM_Image_Extractor();
        $data      = $extractor->collect_base_data_from_page($page->ID);

        $page_usage_data = $data['page_usage_data'][$page->ID] ?? array();

        $all_scan_data[$page->ID] = $data;

        if (!empty($data['duplicates'])) {
            foreach ($data['duplicates'] as $primary_id => $dups) {
                if (!isset($global_duplicates[$primary_id])) {
                    $global_duplicates[$primary_id] = array();
                }
                $global_duplicates[$primary_id] = array_merge($global_duplicates[$primary_id], $dups);
            }
        }

        if (!empty($data['scan_summary'])) {
            $total_count += $data['scan_summary']['count'] ?? 0;

            foreach ($data['scan_summary'] as $key => $value) {
                if ($key === 'count') {
                    continue;
                }

                if ($key === 'widgets_found' && is_array($value)) {
                    if (!isset($global_debug_info['widgets_found'])) {
                        $global_debug_info['widgets_found'] = array();
                    }
                    foreach ($value as $widget => $count) {
                        $global_debug_info['widgets_found'][$widget] = ($global_debug_info['widgets_found'][$widget] ?? 0) + $count;
                    }
                } elseif (is_numeric($value)) {
                    $global_debug_info[$key] = ($global_debug_info[$key] ?? 0) + $value;
                }
            }
        }

        if (empty($page_usage_data)) {
            error_log("  ℹ️ No images found on this page");
            continue;
        }

        $this->process_and_save_images(
            $page->ID,
            $page_usage_data['existing_images'] ?? array(),
            'existing_images',
            $total_images_processed,
            $total_uses_found
        );

        $this->process_and_save_images(
            $page->ID,
            $page_usage_data['missing_in_files'] ?? array(),
            'missing_in_files',
            $total_images_processed,
            $total_uses_found
        );

        $this->process_and_save_images(
            $page->ID,
            $page_usage_data['missing_in_database'] ?? array(),
            'missing_in_database',
            $total_images_processed,
            $total_uses_found
        );
    }

    // ✅ TRUE ORPHANS: files on disk whose BASE_FILENAME is NOT used on any page
    $upload_dir  = wp_upload_dir();
    $upload_base = trailingslashit($upload_dir['basedir']);

    $true_orphans             = array();
    $all_used_base_filenames  = array();

    $file_manager = new PIM_Thumbnail_File_Manager();

    // 1) Collect ALL used base_filenames from pages
    foreach ($all_scan_data as $page_id => $page_data) {
        if (empty($page_data['page_usage_data'][$page_id])) {
            continue;
        }
        $page_usage = $page_data['page_usage_data'][$page_id];

        foreach (array('existing_images', 'missing_in_files', 'missing_in_database') as $cat) {
            if (!empty($page_usage[$cat]) && is_array($page_usage[$cat])) {
                foreach ($page_usage[$cat] as $img) {
                    if (!empty($img['file_url'])) {
                        $base = $file_manager->get_base_filename(basename($img['file_url']));
                        $all_used_base_filenames[$base] = true;
                    }
                }
            }
        }
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($upload_base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $allowed_exts = array('jpg','jpeg','png','webp','gif');

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }

        $full_path = $file->getPathname();
        $filename  = $file->getFilename();
        $ext       = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts, true)) {
            continue;
        }

        if (strpos($full_path, '/cache/') !== false ||
            strpos($full_path, '/thumbs/') !== false ||
            strpos($filename, '.DS_Store') !== false ||
            strpos($filename, '~') !== false) {
            continue;
        }

        $base_filename = $file_manager->get_base_filename(basename($filename));

        if (!isset($all_used_base_filenames[$base_filename])) {
            $true_orphans[] = array(
                'id'          => md5($full_path),
                'size_name'   => '',
                'elementor_id'=> '',
                'source'      => '',
                'file_url'    => $full_path,
                'filesize'    => $file->getSize(),
            );
        }
    }

    error_log('TRUE ORPHANS (base_filename): ' . count($true_orphans));

    update_post_meta(
        PIM_Image_Extractor::GLOBAL_SCAN_POST_ID,
        '_pim_scan_data',
        array(
            'orphaned_files' => $true_orphans,
            'duplicates'     => $global_duplicates,
        )
    );

    $duration = round((microtime(true) - $start_time), 2);

    $scan_summary = array_merge(
        array(
            'timestamp'    => current_time('mysql'),
            'duration'     => $duration,
            'total_pages'  => count($pages),
            'total_images' => $total_images_processed,
            'total_uses'   => $total_uses_found,
            'user'         => wp_get_current_user()->display_name,
        ),
        $global_debug_info,
        array('count' => $total_count)
    );

    update_option('pim_last_scan', $scan_summary);

    $this->save_scan_to_file($all_scan_data, $true_orphans, $global_duplicates, $scan_summary);

    wp_send_json_success(
        array(
            'message'      => sprintf(
                'Scanned %d pages, processed %d images (%d uses)',
                count($pages),
                $total_images_processed,
                $total_uses_found
            ),
            'duration'     => $duration,
            'total_pages'  => count($pages),
            'total_images' => $total_images_processed,
            'total_uses'   => $total_uses_found,
        )
    );
}
