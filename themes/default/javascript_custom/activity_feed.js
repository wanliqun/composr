(function ($cms) {
    'use strict';

    $cms.templates.cnsMemberProfileActivities = function cnsMemberProfileActivities(params, container) {
        var syndications = params.syndications,
            syndication;

        for (var hook in syndications) {
            syndication = syndications[hook];

            if (syndication.syndicationJavascriptFunctionCalls != null) {
                $cms.executeJsFunctionCalls(syndication.syndicationJavascriptFunctionCalls);
            }
        }

        //$syndications[$hook] = array(
        //    'SYNDICATION_IS_SET' => $ob->auth_is_set($member_id_of),
        //    'SYNDICATION_SERVICE_NAME' => $ob->get_service_name(),
        //    'SYNDICATION_JAVASCRIPT_FUNCTION_CALLS' => method_exists($ob, 'syndication_javascript_function_calls') ? $ob->syndication_javascript_function_calls() : ''
        //);
    };

    $cms.templates.activity = function activity(params, container) {
        var liid = strVal(params.liid);

        $cms.dom.on(container, 'click', '.js-submit-confirm-update-remove', function (e) {
            s_update_remove(e, liid);
        });
    };

    $cms.templates.blockMainActivities = function blockMainActivities(params) {
        if (!params.isBlockRaw) {
            window.activities_mode = strVal(params.mode);
            window.activities_member_ids = strVal(params.memberIds);

            if (params.start === 0) {
                // "Grow" means we should keep stacking new content on top of old. If not
                // then we should allow old content to "fall off" the bottom of the feed.
                window.activities_feed_grow = !!params.grow;
                window.activities_feed_max = params.max;
                if (document.getElementById('activities_feed')) {
                    window.setInterval(s_update_get_data, params.refreshTime * 1000);
                }
            }
        }
    };
}(window.$cms));