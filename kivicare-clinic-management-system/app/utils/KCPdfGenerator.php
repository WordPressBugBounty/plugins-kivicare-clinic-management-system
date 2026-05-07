<?php

namespace App\utils;

use Mpdf\Mpdf;
use Mpdf\MpdfException;

if (!defined('ABSPATH')) {
    exit;
}

class KCPdfGenerator
{
    /**
     * Generate a PDF using Mpdf.
     *
     * @param string $html     The HTML content to render.
     * @param string $fileName The name of the output file.
     * @param string $output   The destination (I = inline, D = download, F = local file, S = string).
     * @param array  $config   Additional configuration to override defaults.
     *
     * @return mixed Generated PDF output
     * @throws MpdfException
     */
    public static function generate($html, $fileName = '', $output = 'I', $config = [])
    {
        //  Sanitize filename and provide dynamic fallback
        if (empty($fileName)) {
            $fileName = 'kivicare_document_' . time() . '.pdf';
        }

        $fileName = sanitize_file_name($fileName);

        // Ensure .pdf extension
        if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'pdf') {
            $fileName .= '.pdf';
        }

        // Safe temporary directory configuration
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/kivicare-mpdf-cache';
        if (!is_dir($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // fix: Reverted to native mPDF fonts for Arabic (XB Riyaz) and Hindi (FreeSerif) support
        $defaultConfig = [
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'margin_top'        => 10,
            'margin_bottom'     => 10,
            'margin_left'       => 15,
            'margin_right'      => 15,
            'tempDir'           => $temp_dir,
            'autoScriptToLang'  => true,
            'autoLangToFont'    => true,
            // Point only to the native mPDF ttfonts directory
            'fontDir'           => [
                dirname(__FILE__, 3) . '/vendor/mpdf/mpdf/ttfonts',
            ],
            // Use mPDF's default fontdata - setting backup chain if needed
            'default_font'      => 'dejavusans',
            'backupSubsFont'    => ['dejavusans', 'freeserif', 'xbriyaz', 'taameydavidclm'],
            'backupSIPFont'     => [],
        ];

        // Merge user config with defaults (user config overrides defaults)
        $mergedConfig = array_merge($defaultConfig, $config);

        $mpdf = new Mpdf($mergedConfig);


        // improvement: Permanently disable simpleTables so all templates get full CSS rendering
        // (background-color on th/td, border styles, etc.) without requiring a magic comment
        // in each template file. The old magic-comment approach was fragile and error-prone.
        $mpdf->simpleTables = false;
        $mpdf->packTableData = true;
        $mpdf->shrink_tables_to_fit = 1;

        // Set HTML header if provided
        if (!empty($config['header_html'])) {
            $mpdf->SetHTMLHeader($config['header_html']);
        }

        // Set HTML footer if provided
        if (!empty($config['footer_html'])) {
            $mpdf->SetHTMLFooter($config['footer_html']);
        }

        $mpdf->WriteHTML($html);

        // fix: Clear any buffered output before mPDF sends its PDF Content-Type header.
        // Without this, any stray PHP output (e.g. from plugin hooks or WordPress internals)
        // causes "headers already sent" warnings visible in debug.log (Buffer.php:57).
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Return the Output response (typically a string if $output = 'S', otherwise it sends standard output)
        // Mpdf returns string for 'S', and empty string after sending headers/body for 'I'/'D'/'F'.
        // No exit/echo here as requested.
        return $mpdf->Output($fileName, $output);
    }
}
