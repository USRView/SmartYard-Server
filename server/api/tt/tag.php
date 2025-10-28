<?php

    /**
     * @api {post} /api/tt/tag create tag
     *
     * @apiVersion 1.0.0
     *
     * @apiName createTag
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiBody {Number} projectId
     * @apiBody {String} tag
     * @apiBody {String} color
     *
     * @apiSuccess {Number} tagId
     */

    /**
     * @api {put} /api/tt/tag/:tagId modify tag
     *
     * @apiVersion 1.0.0
     *
     * @apiName modifyTag
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {Number} tagId
     * @apiBody {String} tag
     * @apiBody {String} color
     *
     * @apiSuccess {Boolean} operationResult
     */

    /**
     * @api {delete} /api/tt/tag/:tagId delete tag
     *
     * @apiVersion 1.0.0
     *
     * @apiName deleteTag
     * @apiGroup tt
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {Number} tagId
     *
     * @apiSuccess {Boolean} operationResult
     */

    /**
     * tt api
     */

    namespace api\tt {

        use api\api;

        /**
         * tag method
         */

        class tag extends api {

            public static function POST($params) {
                $tagId = loadBackend("tt")->addTag($params["projectId"], $params["tag"], $params["color"]);

                return api::ANSWER($tagId, ($tagId !== false) ? "tagId" : "notAcceptable");
            }

            public static function PUT($params) {
                $success = loadBackend("tt")->modifyTag($params["_id"], $params["tag"], $params["color"]);

                return api::ANSWER($success, ($success !== false) ? false : "notAcceptable");
            }

            public static function DELETE($params) {
                $success = loadBackend("tt")->deleteTag($params["_id"]);

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
