<?php

    /**
     * @api {get} /api/tt/suggestions get suggestions
     *
     * @apiVersion 1.0.0
     *
     * @apiName suggestions
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiQuery {String} project
     * @apiQuery {String} field
     * @apiQuery {String} field
     *
     * @apiSuccess {Object[]} suggestions
     */

    /**
     * tt api
     */

    namespace api\tt {

        use api\api;

        /**
         * issue field suggestions method
         */

        class suggestions extends api {

            public static function GET($params) {
                $tt = loadBackend("tt");

                if (!$tt) {
                    return API::ERROR(500);
                }

                $suggestions = $tt->getSuggestions($params["project"], $params["field"], $params["query"]);

                return api::ANSWER($suggestions, "suggestions");
            }

            public static function index() {
                if (loadBackend("tt")) {
                    return [
                        "GET" => "#same(tt,issue,GET)",
                    ];
                } else {
                    return false;
                }
            }
        }
    }
