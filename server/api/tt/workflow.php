<?php

    /**
     * @api {get} /api/tt/workflow/:workflowId get workflow
     *
     * @apiVersion 1.0.0
     *
     * @apiName getWorkflow
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {String} workflowId
     *
     * @apiSuccess {Object} body
     */

    /**
     * @api {put} /api/tt/workflow/:workflowId add or modify workflow
     *
     * @apiVersion 1.0.0
     *
     * @apiName modifyWorkflow
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {String} workflowId
     * @apiBody {String} body
     *
     * @apiSuccess {Boolean} operationResult
     */

    /**
     * @api {delete} /api/tt/workflow/:workflowId delete workflow
     *
     * @apiVersion 1.0.0
     *
     * @apiName deleteWorkflow
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {String} workflowId
     *
     * @apiSuccess {Boolean} operationResult
     */

    /**
     * tt api
     */

    namespace api\tt {

        use api\api;

        /**
         * workflow method
         */

        class workflow extends api {

            public static function GET($params) {
                $workflow = loadBackend("tt")->getWorkflow($params["_id"]);

                if ($workflow !== false) {
                    return api::ANSWER($workflow, "body");
                } else {
                    return api::ERROR("inaccessible");
                }
            }

            public static function PUT($params) {
                $success = loadBackend("tt")->putWorkflow($params["_id"], $params["body"]);

                return api::ANSWER($success, ($success !== false) ? false : "notAcceptable");
            }

            public static function DELETE($params) {
                $success = loadBackend("tt")->deleteWorkflow($params["_id"]);

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
