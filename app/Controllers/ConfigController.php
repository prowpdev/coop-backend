<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ConfigRepository;
use PDO;

class ConfigController extends BaseController
{
    private ConfigRepository $config;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->config = new ConfigRepository($db);
    }

    /**
     * GET /api/config/all
     */
    public function all(): never
    {
        $data = $this->config->getAllConfig();
        $this->success($data);
    }

    /**
     * GET /api/branches or /api/config/branches
     */
    public function branches(): never
    {
        $branches = $this->config->getBranches();
        $this->success($branches);
    }

    /**
     * POST /api/branches or /api/config/branches
     */
    public function storeBranch(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['code']) || empty($input['name'])) {
            $this->error('Branch code and name are required.', 422);
        }

        $branch = $this->config->saveBranch($input);
        $this->success($branch, 'Branch saved successfully.', 201);
    }

    /**
     * GET /api/loan-products or /api/config/loan-products
     */
    public function loanProducts(): never
    {
        $products = $this->config->getLoanProducts();
        $this->success($products);
    }

    /**
     * GET /api/savings-products or /api/config/savings-products
     */
    public function savingsProducts(): never
    {
        $products = $this->config->getSavingsProducts();
        $this->success($products);
    }

    /**
     * GET /api/feature-toggles or /api/config/feature-toggles
     */
    public function featureToggles(): never
    {
        $toggles = $this->config->getFeatureToggles();
        $this->success($toggles);
    }

    /**
     * POST /api/feature-toggles
     */
    public function updateToggle(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['feature_key']) || !isset($input['enabled'])) {
            $this->error('Feature key and enabled status are required.', 422);
        }

        $success = $this->config->updateFeatureToggle($input['feature_key'], (bool)$input['enabled']);
        $this->success(['updated' => $success], 'Feature toggle updated.');
    }

    /**
     * GET /api/system-settings or /api/config/system-settings
     */
    public function systemSettings(): never
    {
        $settings = $this->config->getSystemSettings();
        $this->success($settings);
    }
}
