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
    
    public function loanProduct(string $id): never
    {
        $product = $this->config->getLoanProduct($id);
        if (!$product) {
            $this->error('Loan product not found.', 404);
        }
        $this->success($product);
    }

    
    public function getLoanProduct(string $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM loan_products WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
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
     * POST /api/config/feature-toggles/toggle
     */
    public function updateToggle(): never
    {
        $input = $this->getRequestBody();
        $key = $input['feature_key'] ?? $input['key'] ?? null;
        $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : (isset($input['value']) ? (bool)$input['value'] : null);

        if (!$key || $enabled === null) {
            $this->error('Feature key and enabled status are required.', 422);
        }

        $success = $this->config->updateFeatureToggle($key, $enabled);
        $this->success(['updated' => $success, 'feature_key' => $key, 'enabled' => $enabled], 'Feature toggle updated.');
    }

    public function toggleFeature(): never
    {
        $this->updateToggle();
    }

    /**
     * GET /api/system-settings or /api/config/system-settings
     */
    public function systemSettings(): never
    {
        $settings = $this->config->getSystemSettings();
        $this->success($settings);
    }

    /**
     * PUT /api/config/system-settings
     */
    public function updateSystemSettings(): never
    {
        $input = $this->getRequestBody();
        $this->config->updateSystemSettings($input);
        $this->success($input, 'System settings updated successfully.');
    }

    /**
     * POST /api/config/loan-products
     */
    public function storeLoanProduct(): never
    {
        $input = $this->getRequestBody();
        if (empty($input['name'])) {
            $this->error('Loan product name is required.', 422);
        }
        $saved = $this->config->saveLoanProduct($input);
        $this->success($saved, 'Loan product created successfully.', 201);
    }

    /**
     * PUT /api/config/loan-products/:id
     */
    public function updateLoanProduct(string $id): never
    {
        $input = $this->getRequestBody();
        $input['id'] = $id;
        $saved = $this->config->saveLoanProduct($input);
        $this->success($saved, 'Loan product updated successfully.');
    }

    /**
     * DELETE /api/config/loan-products/:id
     */
    public function destroyLoanProduct(string $id): never
    {
        $this->config->deleteLoanProduct($id);
        $this->success(null, 'Loan product deleted successfully.');
    }

    /**
     * POST /api/config/savings-products
     */
    public function storeSavingsProduct(): never
    {
        $input = $this->getRequestBody();
        if (empty($input['name'])) {
            $this->error('Savings product name is required.', 422);
        }
        $saved = $this->config->saveSavingsProduct($input);
        $this->success($saved, 'Savings product created successfully.', 201);
    }

    /**
     * PUT /api/config/savings-products/:id
     */
    public function updateSavingsProduct(string $id): never
    {
        $input = $this->getRequestBody();
        $input['id'] = $id;
        $saved = $this->config->saveSavingsProduct($input);
        $this->success($saved, 'Savings product updated successfully.');
    }

    /**
     * DELETE /api/config/savings-products/:id
     */
    public function destroySavingsProduct(string $id): never
    {
        $this->config->deleteSavingsProduct($id);
        $this->success(null, 'Savings product deleted successfully.');
    }

    /**
     * PUT /api/config/branches/:id
     */
    public function updateBranch(string $id): never
    {
        $input = $this->getRequestBody();
        $input['id'] = $id;
        $saved = $this->config->saveBranch($input);
        $this->success($saved, 'Branch updated successfully.');
    }

    /**
     * GET /api/config/fees
     */
    public function fees(): never
    {
        $fees = $this->config->getFees();
        $this->success($fees);
    }

    /**
     * POST /api/config/fees
     */
    public function storeFee(): never
    {
        $input = $this->getRequestBody();
        $saved = $this->config->saveFee($input);
        $this->success($saved, 'Fee created successfully.', 201);
    }

    /**
     * PUT /api/config/fees/:id
     */
    public function updateFee(string $id): never
    {
        $input = $this->getRequestBody();
        $input['id'] = $id;
        $saved = $this->config->saveFee($input);
        $this->success($saved, 'Fee updated successfully.');
    }

    /**
     * GET /api/config/approval-workflows
     */
    public function approvalWorkflows(): never
    {
        $this->success($this->config->getApprovalWorkflows());
    }

    /**
     * GET /api/config/approval-rules
     */
    public function approvalRules(): never
    {
        $this->success($this->config->getApprovalRules());
    }

    /**
     * POST /api/config/approval-rules
     */
    public function storeApprovalRule(): never
    {
        $input = $this->getRequestBody();
        $saved = $this->config->saveApprovalRule($input);
        $this->success($saved, 'Approval rule created successfully.', 201);
    }

    /**
     * PUT /api/config/approval-rules/:id
     */
    public function updateApprovalRule(string $id): never
    {
        $input = $this->getRequestBody();
        $input['id'] = $id;
        $saved = $this->config->saveApprovalRule($input);
        $this->success($saved, 'Approval rule updated successfully.');
    }

    /**
     * GET /api/config/custom-fields
     */
    public function customFields(): never
    {
        $this->success($this->config->getCustomFields());
    }

    /**
     * POST /api/config/custom-fields
     */
    public function storeCustomField(): never
    {
        $input = $this->getRequestBody();
        $saved = $this->config->saveCustomField($input);
        $this->success($saved, 'Custom field saved successfully.', 201);
    }

    /**
     * PUT /api/config/numbering-formats/:id
     */
    public function updateNumberingFormat(string $id): never
    {
        $input = $this->getRequestBody();
        $saved = $this->config->updateNumberingFormat($id, $input);
        $this->success($saved, 'Numbering format updated successfully.');
    }

    /**
     * PUT /api/config/payment-allocation-rules/:id
     */
    public function updatePaymentAllocationRule(string $id): never
    {
        $input = $this->getRequestBody();
        $saved = $this->config->updatePaymentAllocationRule($id, $input);
        $this->success($saved, 'Payment allocation rule updated successfully.');
    }

    /**
     * GET /api/config/payment-allocation-rules/alloc_cda_std
     */
    public function getAllocationRules(): never
    {
        $this->success($this->config->getAllocationRules());
    }
}
