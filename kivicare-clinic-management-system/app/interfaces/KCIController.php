<?php

namespace App\interfaces;

interface KCIController {
    /**
     * Register REST API routes for this controller
     *
     * @return void
     */
    public function registerRoutes();
    /**
     * Check permissions for routes
     *
     * @param \WP_REST_Request $request Current request object
     * @return bool Whether the request has permission
     */
    public function checkPermission($request);
}
