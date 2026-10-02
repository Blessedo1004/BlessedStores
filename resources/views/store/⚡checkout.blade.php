<?php

use App\Services\CartService;
use App\Services\PaystackService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new class extends Component
{
    #[Layout('components.page-layout')]
    #[Title('Product Checkout')]

    public array $cartItems = [];
    public float $cartTotal = 0;
    public string $state = '';
    public string $city = '';
    public string $address = '';
    public string $name = '';
    public string $email = '';
    public string $phone_number = '';
    public string $postal_code = '';

    public function updated($property)
    {
        $this->validateOnly($property, $this->rules());
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[^<>]*$/'],
            'email' => ['required', 'email'],
            'phone_number' => ['required', 'string', 'regex:/^(?:0\d{10}|\+234\d{10})$/'],
            'state' => ['required', 'string', 'regex:/^[^<>]*$/'],
            'city' => ['required', 'string', 'regex:/^[^<>]*$/'],
            'address' => ['required', 'string', 'regex:/^[^<>]*$/'],
            'postal_code' => ['nullable', 'string', 'regex:/^[^<>]*$/'],
        ];
    }

    public function mount(CartService $cartService)
    {
        $items = $cartService->items();

        if ($items->isEmpty()) {
            return $this->redirect(route('home'), navigate: true);
        }

        // Store scalar display data only; Livewire may hydrate session/model data as arrays.
        $this->cartItems = $items
            ->filter(fn ($item) => $item->product !== null)
            ->map(fn ($item) => [
                'name' => $item->product->name,
                'variant' => $item->variant?->name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->price,
                'line_total' => (float) $item->price * (int) $item->quantity,
            ])
            ->values()
            ->all();

        $this->cartTotal = array_sum(array_column($this->cartItems, 'line_total'));
    }

    public function paystackRedirect(PaystackService $paystack, CartService $cartService)
    {
        $validated = $this->validate();
        $items = $cartService->items();

        if ($items->isEmpty()) {
            $this->addError('payment', 'Your cart is empty. Add an item before placing your order.');
            return;
        }

        // Recalculate from server-side cart data rather than trusting Livewire state.
        $amount = (float) $items->sum(fn ($item) => $item->price * $item->quantity);
        if ($amount < 100) {
            $this->addError('payment', 'The minimum payment amount is ₦100.');
            return;
        }

        $metadata = [
            'name' => $validated['name'],
            'phone' => $validated['phone_number'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'postal_code' => $validated['postal_code'] ?? null,
        ];
        session(['payment_details' => $metadata + ['email' => $validated['email']]]);

        try {
            $response = $paystack->initializeTransaction([
                'email' => $validated['email'],
                'amount' => (int) round($amount * 100),
                'metadata' => $metadata,
                'callback_url' => route('payment.callback'),
            ]);

            $authorizationUrl = data_get($response, 'data.authorization_url');
            if (! data_get($response, 'status') || ! is_string($authorizationUrl) || $authorizationUrl === '') {
                throw new \RuntimeException('Paystack did not return a payment URL.');
            }

            return $this->redirect($authorizationUrl);
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('payment', 'Unable to start payment right now. Please try again.');
        }
    }
};
?>

<div>
      <!-- Breadcrumb area start  -->
      <div class="breadcrumb__area theme-bg-1 p-relative z-index-11 pt-95 pb-95">
         <div class="breadcrumb__thumb" data-background="{{ asset('imgs/bg/breadcrumb-bg.jpg') }}"></div>
         <div class="container">
            <div class="row justify-content-center">
               <div class="col-xxl-12">
                  <div class="breadcrumb__wrapper text-center">
                     <h2 class="breadcrumb__title">Checkout</h2>
                     <div class="breadcrumb__menu">
                        <nav>
                           <ul>
                              <li><span><a href="{{ route('home') }}" wire:navigate>Home</a></span></li>
                              <li><span>checkout</span></li>
                           </ul>
                        </nav>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- Breadcrumb area start  -->

      <!-- checkout-area start -->
      <section class="checkout-area section-space">
         <div class="container">
            <form wire:submit="paystackRedirect">
               <div class="row">
                  <div class="col-lg-6">
                     <div class="checkbox-form">
                        <h3 class="mb-15">Billing Details</h3>
                        <div class="row g-5">
                           <!-- <div class="col-md-12">
                              <div class="country-select">
                                 <label>Country <span class="required">*</span></label>
                                 <select>
                                    <option value="volvo">United States</option>
                                    <option value="saab">Algeria</option>
                                    <option value="mercedes">Afghanistan</option>
                                    <option value="audi">Ghana</option>
                                    <option value="audi2">Albania</option>
                                    <option value="audi3">Bahrain</option>
                                    <option value="audi4">Colombia</option>
                                    <option value="audi5">Dominican Republic</option>
                                 </select>
                              </div>
                           </div> -->
                           <div class="col-md-6">
                              <div class="checkout-form-list">
                                 <label>Name</label>
                                <input type="text" placeholder="John Doe" required wire:model.live.debounce.500ms="name">
                                @error('name')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="checkout-form-list">
                                 <label>Email Address</label>
                                <input type="email" placeholder="email@example.com" required wire:model.live.debounce.500ms="email" inputmode="email">
                                @error('email')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                           </div>
                           </div>
                           <div class="col-md-6">
                              <div class="checkout-form-list">
                                 <label>Phone Number</label>
                                <input type="tel" placeholder="08012345678 or +2348012345678" required wire:model.live.debounce.500ms="phone_number" minlength="11" maxlength="14" autocomplete="tel">
                                @error('phone_number')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="checkout-form-list">
                                 <label>Address</label>
                                <input type="text" placeholder="23 Jump Street" required wire:model.live.debounce.500ms="address">
                                @error('address')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="checkout-form-list">
                                 <label>State</label>
                                <input type="text" placeholder="Abia" required wire:model.live.debounce.500ms="state">
                                @error('state')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="checkout-form-list">
                                 <label>Town / City <span class="required">*</span></label>
                                <input type="text" placeholder="Umuahia" required wire:model.live.debounce.500ms="city">
                                @error('city')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="checkout-form-list">
                                 <label>Postcode / Zip (Optional)</label>
                                <input type="text" placeholder="Postcode / Zip" wire:model.live.debounce.500ms="postal_code" autocomplete="postal-code">
                                @error('postal_code')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                              </div>
                           </div>
                           <!-- <div class="col-md-12">
                              <div class="checkout-form-list create-acc d-flex align-content-center">
                                 <input class="e-check-input" id="xbox" type="checkbox">
                                 <label class="mb-0">Create an account?</label>
                              </div>
                              <div id="cbox_info" class="checkout-form-list create-account">
                                 <p>Create an account by entering the information below. If you are a
                                    returning
                                    customer please login at the top of the page.</p>
                                 <label>Account password <span class="required">*</span></label>
                                 <input type="password" placeholder="password">
                              </div>
                           </div> -->
                        </div>
                        <!-- <div class="different-address">
                           <div class="ship-different-title">
                              <label>Ship to a different address?</label>
                              <input class="e-check-input" id="ship-box" type="checkbox">
                           </div>
                           <div id="ship-box-info">
                              <div class="row">
                                 <div class="col-md-12">
                                    <div class="country-select">
                                       <label>Country <span class="required">*</span></label>
                                       <select>
                                          <option value="volvo">Bangladesh</option>
                                          <option value="saab">Algeria</option>
                                          <option value="mercedes">Afghanistan</option>
                                          <option value="audi">Ghana</option>
                                          <option value="audi2">Albania</option>
                                          <option value="audi3">Bahrain</option>
                                          <option value="audi4">Colombia</option>
                                          <option value="audi5">Dominican Republic</option>
                                       </select>
                                    </div>
                                 </div>
                                 <div class="col-md-6">
                                    <div class="checkout-form-list">
                                       <label>First Name <span class="required">*</span></label>
                                       <input type="text" placeholder="">
                                    </div>
                                 </div>
                                 <div class="col-md-6">
                                    <div class="checkout-form-list">
                                       <label>Last Name <span class="required">*</span></label>
                                       <input type="text" placeholder="">
                                    </div>
                                 </div>
                                 <div class="col-md-12">
                                    <div class="checkout-form-list">
                                       <label>Company Name</label>
                                       <input type="text" placeholder="">
                                    </div>
                                 </div>
                                 <div class="col-md-12">
                                    <div class="checkout-form-list">
                                       <label>Address <span class="required">*</span></label>
                                       <input type="text" placeholder="Street address">
                                    </div>
                                 </div>
                                 <div class="col-md-12">
                                    <div class="checkout-form-list">
                                       <input type="text" placeholder="Apartment, suite, unit etc. (optional)">
                                    </div>
                                 </div>
                                 <div class="col-md-12">
                                    <div class="checkout-form-list">
                                       <label>Town / City <span class="required">*</span></label>
                                       <input type="text" placeholder="Town / City">
                                    </div>
                                 </div>
                                 <div class="col-md-6">
                                    <div class="checkout-form-list">
                                       <label>State / County <span class="required">*</span></label>
                                       <input type="text" placeholder="">
                                    </div>
                                 </div>
                                 <div class="col-md-6">
                                    <div class="checkout-form-list">
                                       <label>Postcode / Zip <span class="required">*</span></label>
                                       <input type="text" placeholder="Postcode / Zip">
                                    </div>
                                 </div>
                                 <div class="col-md-6">
                                    <div class="checkout-form-list">
                                       <label>Email Address <span class="required">*</span></label>
                                       <input type="email" placeholder="">
                                    </div>
                                 </div>
                                 <div class="col-md-6">
                                    <div class="checkout-form-list">
                                       <label>Phone <span class="required">*</span></label>
                                       <input type="text" placeholder="Postcode / Zip">
                                    </div>
                                 </div>
                              </div>
                           </div>
                           <div class="order-notes">
                              <div class="checkout-form-list">
                                 <label>Order Notes</label>
                                 <textarea id="checkout-mess" cols="30" rows="10"
                                    placeholder="Notes about your order, e.g. special notes for delivery."></textarea>
                              </div>
                           </div>
                        </div> -->
                     </div>
                  </div>
                  <div class="col-lg-6">
                     <div class="your-order">
                        <h3>Your order</h3>
                        <div class="your-order-table">
                           <table>
                              <thead>
                                 <tr>
                                    <th class="product-name">Product</th>
                                    <th class="product-total">Total</th>
                                 </tr>
                              </thead>
                              <tbody class="order-items">
                                @foreach($cartItems as $cartItem)
                                    <tr class="cart_item">
                                        <td class="product-name">
                                            {{ $cartItem['name'] }} {{ $cartItem['variant'] ? ' - ' . $cartItem['variant'] : '' }}
                                            <strong class="product-quantity"> × {{ $cartItem['quantity'] }}</strong>
                                        </td>
                                        <td class="product-total">
                                            <span class="amount">₦{{ number_format($cartItem['line_total'], 2) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                              </tbody>
                              <tfoot>
                                 <!-- <tr class="cart-subtotal">
                                    <th>Cart Subtotal</th>
                                    <td><span class="amount">$58.00</span></td>
                                 </tr>
                                 <tr class="shipping">
                                    <th>Shipping</th>
                                    <td>
                                       <ul>
                                          <li>
                                             <input type="radio">
                                             <label>
                                                Flat Rate: <span class="amount">$7.00</span>
                                             </label>
                                          </li>
                                          <li>
                                             <input type="radio">
                                             <label>Free Shipping:</label>
                                          </li>
                                       </ul>
                                    </td>
                                 </tr> -->
                                 <tr class="order-total">
                                    <th>Order Total</th>
                                    <td><strong><span class="amount">₦{{ number_format($cartTotal, 2) }}</span></strong>
                                    </td>
                                 </tr>
                              </tfoot>
                           </table>
                        </div>

                        <div class="payment-method">
                           @error('payment')
                              <div class="alert alert-danger" role="alert">{{ $message }}</div>
                           @enderror
                           <!-- <div class="accordion" id="checkoutAccordion">
                              <div class="accordion-item">
                                 <h2 class="accordion-header" id="checkoutOne">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                       data-bs-target="#bankOne" aria-expanded="true" aria-controls="bankOne">
                                       Direct Bank Transfer
                                    </button>
                                 </h2>
                                 <div id="bankOne" class="accordion-collapse collapse show"
                                    aria-labelledby="checkoutOne" data-bs-parent="#checkoutAccordion">
                                    <div class="accordion-body">
                                       Make your payment directly into our bank account. Please use your
                                       Order ID
                                       as the payment reference. Your order won’t be shipped until the
                                       funds have
                                       cleared in our account.
                                    </div>
                                 </div>
                              </div>
                              <div class="accordion-item">
                                 <h2 class="accordion-header" id="paymentTwo">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                       data-bs-target="#payment" aria-expanded="false" aria-controls="payment">
                                       Cheque Payment
                                    </button>
                                 </h2>
                                 <div id="payment" class="accordion-collapse collapse" aria-labelledby="paymentTwo"
                                    data-bs-parent="#checkoutAccordion">
                                    <div class="accordion-body">
                                       Please send your cheque to Store Name, Store Street, Store Town,
                                       Store
                                       State / County, Store
                                       Postcode.
                                    </div>
                                 </div>
                              </div>
                              <div class="accordion-item">
                                 <h2 class="accordion-header" id="paypalThree">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                       data-bs-target="#paypal" aria-expanded="false" aria-controls="paypal">
                                       PayPal
                                    </button>
                                 </h2>
                                 <div id="paypal" class="accordion-collapse collapse" aria-labelledby="paypalThree"
                                    data-bs-parent="#checkoutAccordion">
                                    <div class="accordion-body">
                                       Pay via PayPal; you can pay with your credit card if you don’t have
                                       a
                                       PayPal account.
                                    </div>
                                 </div>
                              </div>
                           </div> -->
                           <div class="order-button-payment mt-20">
                              <button class="fill-btn" type="submit" wire:loading.attr="disabled">
                                 <span class="fill-btn-inner">
                                 <span class="fill-btn-normal" wire:loading.remove>Continue to payment</span>
                                 <span class="fill-btn-hover" wire:loading.remove>Continue to payment</span>
                                 <span wire:loading>Preparing payment...</span>
                                 </span>
                              </button>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </form>
         </div>
      </section>
      <!-- checkout-area end -->
</div>