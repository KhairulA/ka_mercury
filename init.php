<?php
class ka_mercury extends Plugin
{
    private $host;




    function about()
    {
        return [
            1.0,        // version
            'Attempts to get the full article content via Mercury Web Parse API', // description
            'KhairulA', // author
            false,      // is_system
        ];
    }




    function api_version()
    {
        return 2;
    }




    function init($host)
    {
        $this->host = $host;

        if (version_compare(VERSION_STATIC, '1.8', '<')) {
            user_error('Hooks not registered. Needs at least version 1.8', E_USER_WARNING);
            return;
        }

        $host->add_hook($host::HOOK_PREFS_TABS, $this);
        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
    }




    function hook_article_filter_action($article, $action)
    {
        return $this->process_article($article);
    }




    function hook_article_filter($article)
    {
        return $this->process_article($article);
    }




    function process_article($article)
    {
        $extracted_content = $this->extract_content($article["link"]);

        if ($extracted_content) {
            $article["content"] = $extracted_content;
        }

        return $article;
    }




    function extract_content($url)
    {
        if ($api_key = $this->host->get($this, 'api_key')) {
            try {
                // build request
                $options = [
                    CURLOPT_URL            => 'https://mercury.postlight.com/parser?' . http_build_query(['url' => $url]),
                    CURLOPT_TIMEOUT        => 5,
                    CURLOPT_HEADER         => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'x-api-key: ' . $api_key,
                    ],
                ];

                $s = curl_init();
                curl_setopt_array($s, $options);

                // bail out early on issues
                if (!$result = curl_exec($s)) {
                    return false;
                }

                $content_type = curl_getinfo($s, CURLINFO_CONTENT_TYPE);
                if (strpos($content_type, "application/json") === false) {
                    return false;
                }

                curl_close($s);

                // return the article
                if ($result = json_decode($result)) {
                    return $result->content;
                }

                return false;
            } catch (Exception $e) {
                user_error($e->getMessage(), E_USER_WARNING);
            }
        }
    }




    function hook_prefs_tabs($args)
    {
        print '<div id="' . strtolower(get_class()) . '_ConfigTab" dojoType="dijit.layout.ContentPane"
			href="backend.php?op=pluginhandler&plugin=' . strtolower(get_class()) . '&method=index"
			title="' . __('Mercury') . '"></div>';
    }




    function index()
    {
        $pluginhost = PluginHost::getInstance();
        $api_key    = $pluginhost->get($this, 'api_key');
        ?>
<div data-dojo-type="dijit/layout/AccordionContainer" style="height:100%;">
    <div data-dojo-type="dijit/layout/ContentPane" title="<?php print __('Preferences'); ?>" selected="true">
        <form dojoType="dijit.form.Form" accept-charset="UTF-8" style="overflow:auto;" id="feedcleaner_settings">
        <script type="dojo/method" event="onSubmit" args="evt">
            evt.preventDefault();
            if (this.validate()) {
                var values = this.getValues();
                values.op = "pluginhandler";
                values.method = "save";
                values.plugin = "<?php print strtolower(get_class());?>";
                new Ajax.Request('backend.php', {
                    parameters: dojo.objectToQuery(values),
                    onComplete: function(transport) {
                        if (transport.responseText.indexOf('error')>=0) notify_error(transport.responseText);
                        else notify_info(transport.responseText);
                    }
                });
                //this.reset();
            }
        </script>

        <table width='100%'><tr>
            <td><label>API Key</label></td>
            <td>
                <input dojoType="dijit.form.TextBox" name="api_key" value="<?php echo htmlspecialchars($api_key, ENT_NOQUOTES, 'UTF-8');?>">
            </td>
        </tr></table>

        <p><button dojoType="dijit.form.Button" type="submit"><?php print __("Save");?></button></p>
        </form>
    </div>
</div>
    <?php
    }




    function save()
    {
        $api_key = $_POST['api_key'];

        $this->host->set($this, 'api_key', $api_key);

        echo __("Configuration saved.");
    }
}
