<?php

/**
 * Rah_external_output plugin for Textpattern CMS
 *
 * @author  Jukka Svahn
 * @license GNU GPLv2
 * @link    http://rahforum.biz/plugins/rah_external_output
 *
 * Copyright (C) 2013 Jukka Svahn http://rahforum.biz
 * Licensed under GNU General Public License version 2
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

class Rah_External_Output
{
    /**
     * The installer.
     */

    public function install()
    {
        @$rs = safe_rows(
            'name, content_type, code, allow',
            'rah_external_output',
            '1 = 1'
        );

        if ($rs) {
            foreach ($rs as $a) {
                extract($a);

                $name = ($allow != 'Yes' ? '_' : '') . 'rah_eo_'.$name;

                if (safe_count('txp_form', "name = '".doSlash($name)."'")) {
                    continue;
                }

                $code = ($content_type ? '; Content-type: '.$content_type.n : '') . $code;
    
                @safe_insert(
                    'txp_form',
                    "name = '".doSlash($name)."', type = 'misc', Form = '".doSlash($code)."'"
                );
            }

            @safe_query('DROP TABLE IF EXISTS '.safe_pfx('rah_external_output'));
        }
    }

    /**
     * Uninstaller.
     */

    public function uninstall()
    {
        safe_delete('txp_prefs', "name like 'rah\_external\_output\_%'");
    }

    /**
     * Constructor.
     */

    public function __construct()
    {
        register_callback(array($this, 'install'), 'plugin_lifecycle.rah_external_output', 'installed');
        register_callback(array($this, 'uninstall'), 'plugin_lifecycle.rah_external_output', 'deleted');
        register_callback(array($this, 'view'), 'form');
        register_callback(array($this, 'getSnippet'), 'textpattern');
        register_callback(array($this, 'cleanRequest'), 'txp_die', '404');
    }

    /**
     * Handles clean URLs.
     */

    public function cleanRequest()
    {
        global $pretext;

        if (!gps('rah_external_output') && $name = basename($pretext['request_uri'])) {
            $_GET['rah_external_output'] = $name;
            $this->getSnippet();
        }
    }

    /**
     * Outputs external snippets.
     */

    public function getSnippet()
    {
        global $microstart, $qcount, $qtime, $txptrace, $rah_external_output_mime, $txp_error_code;

        $name = gps('rah_external_output');

        if ($name === '' || !is_string($name)) {
            return;
        }

        $r = safe_field(
            'Form', 
            'txp_form', 
            "name = '".doSlash('rah_eo_'.$name)."'"
        );

        if ($r === false) {
            if ($txp_error_code != 404) {
                txp_die(gTxt('404_not_found'), 404);
            }

            return;
        }

        $mime = array(
            'json' => 'application/json',
            'js'   => 'text/javascript',
            'xml'  => 'text/xml',
            'css'  => 'text/css',
            'txt'  => 'text/plain',
            'html' => 'text/html',
        ) + (array) $rah_external_output_mime;

        ob_clean();
        txp_status_header('200 OK');
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        if ($ext && isset($mime[$ext]))
        {
            header('Content-type: '.$mime[$ext].'; charset=utf-8');
        }

        $lines = explode(n, $r);

        foreach ($lines as $line) {
            if (strpos($line, ';') !== 0) {
                break;
            }

            header(trim(substr(array_shift($lines), 1)));
        }

        set_error_handler('tagErrorHandler');
        echo parse(parse(implode(n, $lines)));
        restore_error_handler();

        if ($ext == 'html' && get_pref('production_status') == 'debug') {
            echo 
                n.comment('Runtime: '.substr(getmicrotime() - $microstart, 0, 6)).
                n.comment('Query time: '.sprintf('%02.6f', $qtime)).
                n.comment('Queries: '.$qcount).
                maxMemUsage('end of textpattern()', 1).
                n.comment('txp tag trace: '.n.str_replace('--', '&shy;&shy;', implode(n, (array) $txptrace)));
        }

        callback_event('rah_external_output.snippet_end');
        exit;
    }

    /**
     * Adds a view link to the form editor.
     */

    public function view()
    {
        $view = escape_js(gTxt('view'));
        $hu = escape_js(hu);

        $js = <<<EOF
            $(document).ready(function ()
            {
                var input = $('input[name="name"]');

                if (input.val().indexOf('rah_eo_') !== 0) {
                    return;
                }

                var uri = '{$hu}?rah_external_output=' + input.val().substr(7);
                var link = $('<a href="#">{$view}</a>').attr('href', uri);
                var actions = $('.txp-actions');

                if (actions.length) {
                    actions.append(' ').append(link);
                } else {
                    input.after(link).after(' ');
                }

                link.click(function (e)
                {
                    e.preventDefault();
                    window.open(uri);
                });
            });
EOF;

        echo script_js($js);
    }
}

new Rah_External_Output();
