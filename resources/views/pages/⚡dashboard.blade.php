<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new class extends Component
{
     #[Title('Dashboard')]
}
?>

<div>
            <!-- Dashboard Content Container -->
            <div class="dashboard-content">
                
                <!-- Welcome Banner -->
                {{-- <div class="card border-0 rounded-4 overflow-hidden mb-4 shadow-sm text-white" style="background: linear-gradient(135deg, #2b221a 0%, #1a1510 100%);">
                    <div class="card-body p-4 p-md-5 position-relative">
                        <!-- Dynamic Background shapes -->
                        <div class="position-absolute end-0 bottom-0 opacity-10" style="transform: translate(10%, 10%);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" fill="var(--primary-color)" viewBox="0 0 24 24">
                                <path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                        <div class="position-relative z-index-1 max-w-xl">
                            <span class="badge mb-3 py-2 px-3 fw-bold text-uppercase" style="background-color: var(--primary-color); font-size: 0.7rem; letter-spacing: 0.5px;">Merchant Console</span>
                            <h2 class="fw-bold mb-2" style="font-family: 'Sora', sans-serif;">Good afternoon, Blessed Victor-Igwe!</h2>
                            <p class="text-white-50 mb-0">Here is what is happening in the Blessed Store today. You have 12 pending orders and 4 requests awaiting your review.</p>
                        </div>
                    </div>
                </div> --}}

                <!-- Statistics Overview Grid -->
                <div class="row g-4 mb-4">
                    <!-- Stat Card 1 -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Total Revenue</span>
                                <h3 class="fw-bold my-1 text-dark" style="font-family: 'Sora', sans-serif;">$48,259.00</h3>
                                <span class="text-success small fw-semibold d-flex align-items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span>+14.5% vs last month</span>
                                </span>
                            </div>
                            <div class="stat-icon-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Stat Card 2 -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Active Orders</span>
                                <h3 class="fw-bold my-1 text-dark" style="font-family: 'Sora', sans-serif;">356</h3>
                                <span class="text-success small fw-semibold d-flex align-items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span>+8.2% vs yesterday</span>
                                </span>
                            </div>
                            <div class="stat-icon-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Stat Card 3 -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">New Customers</span>
                                <h3 class="fw-bold my-1 text-dark" style="font-family: 'Sora', sans-serif;">1,280</h3>
                                <span class="text-success small fw-semibold d-flex align-items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span>+18.3% vs last week</span>
                                </span>
                            </div>
                            <div class="stat-icon-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Stat Card 4 -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Avg. Order Value</span>
                                <h3 class="fw-bold my-1 text-dark" style="font-family: 'Sora', sans-serif;">$135.50</h3>
                                <span class="text-danger small fw-semibold d-flex align-items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                                    </svg>
                                    <span>-1.2% vs last month</span>
                                </span>
                            </div>
                            <div class="stat-icon-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visuals and Data Feeds -->
                <div class="row g-4 mb-4">
                    <!-- Column Left: Sales Chart -->
                    <div class="col-12 col-lg-8">
                        <div class="chart-card">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div>
                                    <h4 class="fw-bold text-dark mb-1 h5" style="font-family: 'Sora', sans-serif;">Weekly Sales Volume</h4>
                                    <p class="text-muted small mb-0">Total volume of sales processed during this week</p>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" id="chartFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                        This Week
                                    </button>
                                </div>
                            </div>
                            <!-- Styled SVG Bar Chart -->
                            <div class="d-flex justify-content-between align-items-end px-3 pt-3" style="height: 240px; border-bottom: 1px solid #ebe8e2;">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="chart-bar" style="height: 120px;"></div>
                                    <span class="text-muted small mt-2 fw-semibold">Mon</span>
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <div class="chart-bar" style="height: 160px;"></div>
                                    <span class="text-muted small mt-2 fw-semibold">Tue</span>
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <div class="chart-bar" style="height: 90px;"></div>
                                    <span class="text-muted small mt-2 fw-semibold">Wed</span>
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <div class="chart-bar" style="height: 210px; background-color: #2b221a;"></div>
                                    <span class="text-muted small mt-2 fw-semibold">Thu</span>
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <div class="chart-bar" style="height: 140px;"></div>
                                    <span class="text-muted small mt-2 fw-semibold">Fri</span>
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <div class="chart-bar" style="height: 180px;"></div>
                                    <span class="text-muted small mt-2 fw-semibold">Sat</span>
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <div class="chart-bar" style="height: 80px;"></div>
                                    <span class="text-muted small mt-2 fw-semibold">Sun</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column Right: Recent Activity -->
                    <div class="col-12 col-lg-4">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <h4 class="fw-bold text-dark mb-4 h5" style="font-family: 'Sora', sans-serif;">System Activity</h4>
                                
                                <div class="d-flex flex-column gap-4">
                                    <!-- Activity Item 1 -->
                                    <div class="d-flex gap-3">
                                        <div class="flex-shrink-0">
                                            <span class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white" style="width: 36px; height: 36px;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div>
                                            <p class="mb-0 text-dark fw-semibold small">Product "Premium Golden Roast Coffee" added</p>
                                            <span class="text-muted" style="font-size: 0.75rem;">12 minutes ago</span>
                                        </div>
                                    </div>

                                    <!-- Activity Item 2 -->
                                    <div class="d-flex gap-3">
                                        <div class="flex-shrink-0">
                                            <span class="rounded-circle d-flex align-items-center justify-content-center bg-info text-white" style="width: 36px; height: 36px;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div>
                                            <p class="mb-0 text-dark fw-semibold small">Payment callback verified via Paystack</p>
                                            <span class="text-muted" style="font-size: 0.75rem;">45 minutes ago</span>
                                        </div>
                                    </div>

                                    <!-- Activity Item 3 -->
                                    <div class="d-flex gap-3">
                                        <div class="flex-shrink-0">
                                            <span class="rounded-circle d-flex align-items-center justify-content-center bg-warning text-white" style="width: 36px; height: 36px;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div>
                                            <p class="mb-0 text-dark fw-semibold small">Low stock warning: "Organic Honey Extract"</p>
                                            <span class="text-muted" style="font-size: 0.75rem;">2 hours ago</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders Data Table -->
                <div class="custom-table-container">
                    <div class="p-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                        <h4 class="fw-bold text-dark mb-0 h5" style="font-family: 'Sora', sans-serif;">Recent Transactions</h4>
                        <a href="#" class="text-color-1 text-decoration-none fw-semibold small">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table custom-table mb-0 w-100">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-semibold text-dark">#ORD-94827</td>
                                    <td>Chinwe Okafor</td>
                                    <td>Luxury Furniture Armchair</td>
                                    <td class="fw-bold text-dark">$320.00</td>
                                    <td><span class="badge-custom-success">Delivered</span></td>
                                    <td>June 22, 2026</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">#ORD-94826</td>
                                    <td>Victor Adewale</td>
                                    <td>Organic Honey (Pack of 3)</td>
                                    <td class="fw-bold text-dark">$45.00</td>
                                    <td><span class="badge-custom-warning">Pending</span></td>
                                    <td>June 22, 2026</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">#ORD-94825</td>
                                    <td>Blessed Victor-Igwe</td>
                                    <td>Premium Golden Roast Coffee</td>
                                    <td class="fw-bold text-dark">$120.00</td>
                                    <td><span class="badge-custom-info">Processing</span></td>
                                    <td>June 21, 2026</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">#ORD-94824</td>
                                    <td>Fatima Ibrahim</td>
                                    <td>Pharmacy First-Aid Kit Box</td>
                                    <td class="fw-bold text-dark">$85.00</td>
                                    <td><span class="badge-custom-success">Delivered</span></td>
                                    <td>June 20, 2026</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
</div>