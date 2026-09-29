<?php

use Livewire\Component;
use App\Models\Store;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new class extends Component
{
    #[Layout('components.page-layout')] 
    #[Title('Store Details')]

    public Store $store;


    public function mount($slug){
        $this->store = Store::with('user')->where('slug', $slug)->firstOrFail();
    }
};
?>

<div>
    <!-- Breadcrumb area start  -->
      <div class="breadcrumb__area theme-bg-1 p-relative z-index-11 pt-95 pb-95">
         <div class="breadcrumb__thumb" data-background="{{ asset('imgs/bg/breadcrumb-bg-furniture.jpg') }}"></div>
         <div class="container">
            <div class="row justify-content-center">
               <div class="col-xxl-12">
                  <div class="breadcrumb__wrapper text-center">
                     <h2 class="breadcrumb__title">Store Details</h2>
                     <div class="breadcrumb__menu">
                        <nav>
                           <ul>
                              <li><span><a href="{{ route('home') }}" wire:navigate>Home</a></span></li>
                              <li><span>Store Details</span></li>
                           </ul>
                        </nav>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- Breadcrumb area start  -->

      <!-- Product details area start -->
      <div class="product__details-area section-space-medium">
         <div class="container">
            <div class="row align-items-center d-flex justify-content-center">
               <div class="col-xxl-4 col-lg-4">
                  <div class="product__details-thumb-big w-img mr-50 text-center">
                     <img src="{{ $store->logo ? asset('storage/' . $store->logo) : asset('imgs/logo/logo.png') }}"
                        alt="{{ $store->name }} logo" style="max-height: 420px; width: 100%; object-fit: contain;">
                  </div>
               </div>
               <div class="col-xxl-6 col-lg-6">
                  <div class="product__details-content pr-80">
                     <div class="product__details-top d-sm-flex align-items-center mb-15">
                        <div class="product__details-rating mr-10">
                           <a href="#"><i class="fa-solid fa-star"></i></a>
                           <a href="#"><i class="fa-solid fa-star"></i></a>
                           <a href="#"><i class="fa-regular fa-star"></i></a>
                        </div>
                        <div class="product__details-review-count">
                           <a href="#">10 Reviews</a>
                        </div>
                     </div>
                     <h3 class="product__details-title text-capitalize">{{ $store->name }}</h3>
                     <div class="product__details-meta mb-20">
                        <div>
                           <span>Store Owner:  {{ $store->user->name }}</span>
                        </div>
                        <div>
                           <span>Address:  {{ $store->address }}</span>
                        </div>
                        <div>
                           <span>Phone Number:  {{ $store->phone_number }}</span>
                        </div>
                     </div>
                     <div class="product__details-share">
                        <span>Share:</span>
                        <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#"><i class="fa-brands fa-twitter"></i></a>
                        <a href="#"><i class="fa-brands fa-behance"></i></a>
                        <a href="#"><i class="fa-brands fa-youtube"></i></a>
                        <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
                     </div>
                  </div>
               </div>
            </div>
            <div class="product__details-additional-info section-space-medium-top">
               <div class="row">
                  <div class="col-xxl-3 col-xl-4 col-lg-4">
                     <div class="product__details-more-tab mr-15">
                        <nav>
                           <div class="nav nav-tabs flex-column " id="productmoretab" role="tablist">
                              <button class="nav-link active" id="nav-description-tab" data-bs-toggle="tab"
                                 data-bs-target="#nav-description" type="button" role="tab"
                                 aria-controls="nav-description" aria-selected="true">Description</button>
                              <button class="nav-link" id="nav-additional-tab" data-bs-toggle="tab"
                                 data-bs-target="#nav-additional" type="button" role="tab"
                                 aria-controls="nav-additional" aria-selected="false">Top Products </button>
                              <button class="nav-link" id="nav-review-tab" data-bs-toggle="tab"
                                 data-bs-target="#nav-review" type="button" role="tab" aria-controls="nav-review"
                                 aria-selected="false">Reviews (3)</button>
                           </div>
                        </nav>
                     </div>
                  </div>
                  <div class="col-xxl-9 col-xl-8 col-lg-8">
                     <div class="product__details-more-tab-content">
                        <div class="tab-content" id="productmorecontent">
                           <div class="tab-pane fade show active" id="nav-description" role="tabpanel"
                              aria-labelledby="nav-description-tab">
                              <div class="product__details-des">
                                 <p>{{ $store->description }}</p>
                              </div>
                           </div>
                           <div class="tab-pane fade" id="nav-additional" role="tabpanel"
                              aria-labelledby="nav-additional-tab">
                              <div class="product__details-info">
                                 <ul>
                                    <li>
                                       <h4>Weight</h4>
                                       <span>2 lbs</span>
                                    </li>
                                    <li>
                                       <h4>Dimensions</h4>
                                       <span>12 × 16 × 19 in</span>
                                    </li>
                                    <li>
                                       <h4>Product</h4>
                                       <span>Purchase this product on rag-bone.com</span>
                                    </li>
                                    <li>
                                       <h4>Color</h4>
                                       <span>Gray, Black</span>
                                    </li>
                                    <li>
                                       <h4>Size</h4>
                                       <span>S, M, L, XL</span>
                                    </li>
                                    <li>
                                       <h4>Model</h4>
                                       <span>Model </span>
                                    </li>
                                    <li>
                                       <h4>Shipping</h4>
                                       <span>Standard shipping: $5,95</span>
                                    </li>
                                    <li>
                                       <h4>Care Info</h4>
                                       <span>Machine Wash up to 40ºC/86ºF Gentle Cycle</span>
                                    </li>
                                    <li>
                                       <h4>Brand</h4>
                                       <span>Kazen</span>
                                    </li>
                                 </ul>
                              </div>
                           </div>
                           <div class="tab-pane fade" id="nav-review" role="tabpanel" aria-labelledby="nav-review-tab">
                              <div class="product__details-review">
                                 <h3 class="comments-title">03 reviews for “Wide Cotton Tunic extreme hammer”</h3>
                                 <div class="latest-comments mb-50">
                                    <ul>
                                       <li>
                                          <div class="comments-box d-flex">
                                             <div class="comments-avatar mr-10">
                                                <img src="assets/imgs/user/user-01.png" alt="">
                                             </div>
                                             <div class="comments-text">
                                                <div
                                                   class="comments-top d-sm-flex align-items-start justify-content-between mb-5">
                                                   <div class="avatar-name">
                                                      <h5>Siarhei Dzenisenka</h5>
                                                      <div class="comments-date">
                                                         <span>March 27, 2018 9:51 am</span>
                                                      </div>
                                                   </div>
                                                   <div class="user-rating">
                                                      <ul>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fal fa-star"></i></a></li>
                                                      </ul>
                                                   </div>
                                                </div>
                                                <p>This is cardigan is a comfortable warm classic piece. Great to layer
                                                   with a light top and you can dress up or down given the jewel
                                                   buttons. I’m 5’8” 128lbs a 34A and the Small fit fine.</p>
                                             </div>
                                          </div>
                                       </li>
                                       <li>
                                          <div class="comments-box d-flex">
                                             <div class="comments-avatar mr-10">
                                                <img src="assets/imgs/user/user-02.png" alt="">
                                             </div>
                                             <div class="comments-text">
                                                <div
                                                   class="comments-top d-sm-flex align-items-start justify-content-between mb-5">
                                                   <div class="avatar-name">
                                                      <h5>Siarhei Dzenisenka</h5>
                                                      <div class="comments-date">
                                                         <span>March 27, 2018 9:51 am</span>
                                                      </div>
                                                   </div>
                                                   <div class="user-rating">
                                                      <ul>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                      </ul>
                                                   </div>
                                                </div>
                                                <p>I bought this beautiful pale gray cashmere sweater for my
                                                   daughter-in-law for her birthday. She loves it and can wear it with
                                                   almost anything!</p>
                                             </div>
                                          </div>
                                       </li>
                                       <li>
                                          <div class="comments-box d-flex">
                                             <div class="comments-avatar mr-10">
                                                <img src="assets/imgs/user/user-03.png" alt="">
                                             </div>
                                             <div class="comments-text">
                                                <div
                                                   class="comments-top d-sm-flex align-items-start justify-content-between mb-5">
                                                   <div class="avatar-name">
                                                      <h5>Siarhei Dzenisenka</h5>
                                                      <div class="comments-date">
                                                         <span>March 27, 2018 9:51 am</span>
                                                      </div>
                                                   </div>
                                                   <div class="user-rating">
                                                      <ul>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fal fa-star"></i></a></li>
                                                      </ul>
                                                   </div>
                                                </div>
                                                <p>Amazing club. Sure the secruity is very tight but actually made me
                                                   and my friends feel secure. You just have to go along with the
                                                   secruity. Bar staff and cloakroom staff really friendly. Coming out
                                                   at 7am into bright London sunshine in Smithfields is an amazing
                                                   London experience</p>
                                             </div>
                                          </div>
                                       </li>
                                    </ul>
                                 </div>
                                 <div class="product__details-comment section-space-medium-bottom">
                                    <div class="comment-title mb-20">
                                       <h3>Add a review</h3>
                                       <p>Your email address will not be published. Required fields are marked *</p>
                                    </div>
                                    <div class="comment-rating mb-20">
                                       <span>Overall ratings</span>
                                       <ul>
                                          <li><a href="#"><i class="fas fa-star"></i></a></li>
                                          <li><a href="#"><i class="fas fa-star"></i></a></li>
                                          <li><a href="#"><i class="fas fa-star"></i></a></li>
                                          <li><a href="#"><i class="fas fa-star"></i></a></li>
                                          <li><a href="#"><i class="fal fa-star"></i></a></li>
                                       </ul>
                                    </div>
                                    <div class="comment-input-box">
                                       <form action="#">
                                          <div class="row">
                                             <div class="col-xxl-12">
                                                <div class="comment-input">
                                                   <textarea placeholder="Your review"></textarea>
                                                </div>
                                             </div>
                                             <div class="col-xxl-6">
                                                <div class="comment-input">
                                                   <input type="text" placeholder="Your Name*">
                                                </div>
                                             </div>
                                             <div class="col-xxl-6">
                                                <div class="comment-input">
                                                   <input type="email" placeholder="Your Email*">
                                                </div>
                                             </div>
                                             <div class="col-xxl-12">
                                                <div class="comment-agree d-flex align-items-center mb-25">
                                                   <input class="z-check-input" type="checkbox" id="z-agree">
                                                   <label class="z-check-label" for="z-agree">Save my name, email, and
                                                      website in this browser for the next time I comment.</label>
                                                </div>
                                             </div>
                                             <div class="col-xxl-12">
                                                <div class="comment-submit">
                                                   <button type="submit" class="fill-btn">
                                                      <span class="fill-btn-inner">
                                                         <span class="fill-btn-normal">submit now</span>
                                                         <span class="fill-btn-hover">submit now</span>
                                                      </span>
                                                   </button>
                                                </div>
                                             </div>
                                          </div>
                                       </form>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- Store details area end -->
</div>