<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new class extends Component
{
     #[Title('Stores')]
}
?>

<div>
    <div class="dashboard-content">

        <div class="row d-flex align-items-center justify-content-between mb-4">
            <div class="col-12 col-lg-4 text-center text-lg-start">
                <h4 class="fw-bold text-dark mb-1 h5">Stores</h4>
                <p class="text-muted small mb-0">Manage merchant stores — add, search, and view store data.</p>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row d-flex justify-content-md-center justify-content-lg-end">
                    <div class="col-12 col-md-4 mt-4 mt-lg-0">
                        <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search stores by name" />
                    </div>
                    <div class="col-12 col-md-6 mt-4 mt-lg-0 text-center">
                        <a href="{{ route('stores.add') }}" class="fill-btn border-0" wire:navigate>
                                <span class="fill-btn-inner">
                                    <span class="fill-btn-normal">Add Store</span>
                                    <span class="fill-btn-hover">Add Store</span>
                                </span>
                        </a>
                    </div>
                </div>

            </div>


        </div>

        <div class="custom-table-container">
            <div class="p-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                <h4 class="fw-bold text-dark mb-0 h5">Stores</h4>
                <span class="text-muted small">Total: 0</span>
            </div>

            <div class="table-responsive">
                <table class="table custom-table mb-0 w-100">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone Number</th>
                            <th>Logo</th>
                            <th>Social Media 1</th>
                            <th>Social Media 2</th>
                            <th>Social Media 3</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold text-dark">Sample Store</td>
                            <td>+234 800 000 0000</td>
                            <td><img src="/imgs/app/placeholder.png" alt="logo" class="rounded" style="height:40px; width:auto;"></td>
                            <td><a href="#" class="text-color-1 text-decoration-none">Instagram</a></td>
                            <td><a href="#" class="text-color-1 text-decoration-none">Facebook</a></td>
                            <td><a href="#" class="text-color-1 text-decoration-none">Twitter</a></td>
                            <td class="text-end">
                                <a href="#" class="btn btn-outline-secondary btn-sm rounded-pill">Edit</a>
                                <a href="#" class="btn btn-outline-danger btn-sm rounded-pill ms-2">Delete</a>
                            </td>
                        </tr>

                        <!-- Placeholder / empty state rows to roughly match 6 rows layout -->
                        <tr>
                            <td colspan="7" class="text-muted text-center py-4">No more stores found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
