<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MemberRepository;
use PDO;

class MemberController extends BaseController
{
    private MemberRepository $members;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->members = new MemberRepository($db);
    }

    /**
     * GET /api/members
     */
    public function index(): never
    {
        $branchId = $this->getQuery('branchId');
        $status   = $this->getQuery('status');
        $search   = $this->getQuery('search');

        $result = $this->members->all($branchId, $status, $search);
        $this->json([
            'success' => true,
            'data'    => $result,
            'total'   => count($result)
        ]);
    }

    /**
     * GET /api/members/:id
     */
    public function show(string $id): never
    {
        $member = $this->members->find($id);
        if (!$member) {
            $this->error('Member not found', 404);
        }

        $this->success($member);
    }

    /**
     * POST /api/members
     */
    public function store(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['first_name']) || empty($input['last_name'])) {
            $this->error('First name and last name are required.', 422);
        }

        if (empty($input['branch_id'])) {
            $this->error('Branch assignment is required.', 422);
        }

        $created = $this->members->create($input);
        $this->success($created, 'Member registered successfully.', 201);
    }

    /**
     * PUT /api/members/:id
     */
    public function update(string $id): never
    {
        $input = $this->getRequestBody();
        $updated = $this->members->update($id, $input);

        if (!$updated) {
            $this->error('Member not found or no changes made.', 404);
        }

        $this->success($updated, 'Member updated successfully.');
    }

    /**
     * DELETE /api/members/:id
     */
    public function destroy(string $id): never
    {
        $deleted = $this->members->delete($id);
        if (!$deleted) {
            $this->error('Failed to delete member or member not found.', 400);
        }

        $this->success(['id' => $id], 'Member deleted successfully.');
    }

    /**
     * GET /api/members/:id/report
     */
    public function report(string $id): never
    {
        $report = $this->members->getMemberReport($id);
        if (empty($report)) {
            $this->error('Member not found.', 404);
        }

        $this->success($report);
    }
}
