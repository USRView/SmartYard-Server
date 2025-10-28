<?php

    /**
     * @api {get} /api/tt/lib/:libId get workflow lib
     *
     * @apiVersion 1.0.0
     *
     * @apiName getWorkflowLib
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {String} libId
     *
     * @apiSuccess {Object} body
     */

    /**
     * @api {put} /api/tt/lib/:libId modify (add) workflow lib
     *
     * @apiVersion 1.0.0
     *
     * @apiName putWorkflowLib
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {String} libId
     * @apiBody {String} body
     *
     * @apiSuccess {Boolean} operationResult
     */

    /**
     * @api {delete} /api/tt/lib/:libId delete workflow lib
     *
     * @apiVersion 1.0.0
     *
     * @apiName deleteWorkflowLib
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {String} libId
     *
     * @apiSuccess {Boolean} operationResult
     */

    /**
     * tt api
     */

    namespace api\tt {

        use api\api;

        /**
         * lib method
         */

        class lib extends api {

            public static function GET($params) {
                $lib = loadBackend("tt")->getWorkflowLib($params["_id"]);

                if ($lib !== false) {
                    return api::ANSWER($lib, "body");
                } else {
                    return api::ERROR("inaccessible");
                }
            }

            public static function PUT($params) {
                $success = loadBackend("tt")->putWorkflowLib($params["_id"], $params["body"]);

                return api::ANSWER($success, ($success !== false) ? false : "notAcceptable");
            }

            public static function DELETE($params) {
                $success = loadBackend("tt")->deleteWorkflowLib($params["_id"]);

                return api::ANSWER($success, ($success !== false) ? false : "notAcceptable");
            }

            public static function index() {
                if (loadBackend("tt")) {
                    return [
                        "GET" => "#same(tt,tt,GET)",
                        "PUT" => "#same(tt,project,PUT)",
                        "DELETE" => "#same(tt,project,DELETE)",
                    ];
                } else {
                    return false;
                }
            }
        }
    }
