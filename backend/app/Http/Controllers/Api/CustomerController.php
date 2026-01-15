<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends BaseApiController
{
    /**
     * Display a listing of customers with pagination and search
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        $customers = $query->latest()->paginate($request->get('per_page', 15));

        return CustomerResource::collection($customers);
    }

    /**
     * Store a newly created customer
     */
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        return $this->successResponse(
            new CustomerResource($customer),
            'Customer created successfully',
            201
        );
    }

    /**
     * Display the specified customer
     */
    public function show(Customer $customer)
    {
        return $this->successResponse(new CustomerResource($customer));
    }

    /**
     * Update the specified customer
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return $this->successResponse(
            new CustomerResource($customer),
            'Customer updated successfully'
        );
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return $this->successResponse(null, 'Customer deleted successfully');
    }
}
