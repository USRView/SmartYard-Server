<?php

    /**
     * @api {post} /api/tt/resolution create resolution
     *
     * @apiVersion 1.0.0
     *
     * @apiName createResolution
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiBody {String} resolution
     *
     * @apiSuccess {Number} resolutionId
     */

    /**
     * @api {put} /api/tt/resolution/:resolutionId modify resolution
     *
     * @apiVersion 1.0.0
     *
     * @apiName modifyResolution
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {Number} resolutionId
     * @apiBody {String} resolution
     *
     * @apiSuccess {Boolean} operationResult
     */

    /**
     * @api {delete} /api/tt/resolution/:resolutionId delete resolution
     *
     * @apiVersion 1.0.0
     *
     * @apiName deleteResolution
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {Number} resolutionId
     *
     * @apiSuccess {Boolean} operationResult
     */

    /**
     * tt api
     */

    namespace api\tt {

        use api\api;

        /**
         * resolution method
         */

        class resolution extends api {

            public static function POST($params) {
                $resolutionId = loadBackend("tt")->addResolution($params["resolution"]);

                return api::ANSWER($resolutionId, ($resolutionId !== false) ? "resolutionId" : "notAcceptable");
            }

            public static function PUT($params) {
                $success = loadBackend("tt")->modifyResolution($params["_id"], $params["resolution"]);

                return api::ANSWER($success, ($success !== false) ? false : "notAcceptable");
            }

            public static function DELETE($params) {
                $success = loadBackend("tt")->deleteResolution($params["_id"]);

                return api::ANSWER($success, ($success !== false) ? false : "notAcceptable");
            }

            public static function index() {
                if (loadBackend("tt")) {
                    return [
                        "POST" => "#same(tt,project,POST)",
                        "PUT" => "#same(tt,project,PUT)",
                        "DELETE" => "#same(tt,project,DELETE)",
                    ];
                } else {
                    return false;
                }
            }
        }
    }
