<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Store;
use App\Models\Social;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\StoreRegistrationEmail;

new class extends Component
{
    use WithFileUploads;
    #[Title('Edit Store')]

    public Store $store;
    public string $name ;
    public string $email = '';
    public string $store_name = '';
    public string $phone_number ;
    public $logo;
    public $newLogo;
    public string $description ;
    public string $address ;
    public string $platform ;
    public string $user_name ;
    public array $social_media;
    public string $status = 'active';


    public function mount($slug){
        $store = Store::with('user','socials')->where('slug', $slug)->firstOrFail();
        $this->store = $store;
        $this->name = $store->user->name;
        $this->phone_number = $store->phone_number;
        $this->logo = $store->logo;
        $this->description = $store->description;
        $this->address = $store->address;
        $this->status = $store->status;
        $socials = $store->socials;
        foreach ($socials as $social) {
            $this->social_media[] = [
                'platform' => $social->platform,
                'user_name' => $social->user_name,
        ];
        }
    }

    public function updated($property)
    {
        $this->validateOnly($property, $this->rules());
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[^<>]*$/',
            ],

            'email' => [
                'nullable',
                'email',
                'unique:users,email',
            ],
            'store_name' => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                'unique:stores,name',
                'regex:/^[^<>]*$/',
            ],
            'phone_number' => [
                'required',
                'string',
                'size:11',
                'regex:/^0[0-9]{10}$/'
            ],
             'newLogo' => [
                'nullable',
                'image',
                'max:2048'
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:100',
                'regex:/^[^<>]*$/',
            ],
            'address' => [
                'required',
                'string',
                'min:10',
                'max:100',
                'regex:/^[^<>]*$/',
            ],
            'status' => [
                'required',
                'in:active,suspended',
            ],
            'social_media' => [
                'required',
                'array',
                'min:1',
                'max:5'
            ],

    ];
    }

    public function addSocialMedia()
    {
        if (count($this->social_media) >= 5) {
            $this->addError('social_media', 'You may only add up to 5 social media information.');
            return;
        }

        // Validate the platform and user_name fields
        $this->validate([
            'platform' => 'required|string',
            'user_name' => 'required|string',
        ]);

        // Add the social media platform and user_name to the social_media array
        $this->social_media[] = [
            'platform' => $this->platform,
            'user_name' => $this->user_name,
        ];

        $this->platform = '';
        $this->user_name = '';
    }

    public function removeSocialMedia($index)
    {
        // Remove the social media entry at the specified index
        unset($this->social_media[$index]);
        // Re-index the array to maintain proper indices
        $this->social_media = array_values($this->social_media);
    }

    public function save(){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('admin-or-super-admin') && $this->store->user_id !== auth()->id()) {
            abort(403);
        }

       // Rate limiting
       $key = 'update-store:' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'update',
                "You can only update five stores in 5 minutes. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address.
        RateLimiter::hit($key, 60 * 5);

        // Validation
        $this->validate($this->rules());

        return DB::transaction(function () {
            $this->store->user->name = $this->name;
            if(filled($this->email)){
               $this->store->user->email = $this->email;
            }

            $this->store->user->save();

            if(filled($this->store_name)){
               $this->store->name = $this->store_name;
            }
            
            if(filled($this->newLogo)){
                $newPath = $this->newLogo->store('logos','public');
                $this->store->logo = $newPath;
            }

            $this->store->phone_number = $this->phone_number;

            $this->store->description = $this->description;

            $this->store->address = $this->address;
            $this->store->status = $this->status;

            $this->store->socials()->delete();

            $this->store->socials()->createMany($this->social_media);

            $this->store->save();
            session()->flash('store-update-success','Store updated successfully');
            return $this->redirect(route('stores'), navigate:true);
        });
    }
    
};
?>
<div class="dashboard-content">
        @php
            $rateLimitErrors = collect($errors->messages())
                ->except(['name', 'email', 'store_name', 'phone_number', 'logo', 'newLogo', 'description', 'address', 'social_media'])
                ->flatten();
        @endphp 
        
         @if ($rateLimitErrors->isNotEmpty())
            @foreach ($rateLimitErrors as $error)
                <div class="alert alert-danger border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <ul class="mb-0 ps-2 list-unstyled">
                            <li>{{ $error }}</li>
                    </ul>
                </div>
            @endforeach
        @endif

        <div class="mb-4">
            <h5 class="fw-bold text-dark mb-1">Update Store Information</h5>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">

                <form wire:submit="save" class="needs-validation" novalidate>


                    <div class="row g-3 justify-content-center">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Owner Name</label>
                            <input type="text" placeholder="John Doe" required wire:model.live.debounce.500ms="name">
                            @error('name')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Store Name</label>
                            <input type="text" placeholder="Leave blank if you want it unchanged" wire:model.live.debounce.500ms="store_name">
                            @error('store_name')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Contact Email</label>
                            <input type="email" placeholder="Leave blank if you want it unchanged" wire:model.live.debounce.500ms="email" inputmode="email">
                            @error('email')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Phone Number</label>
                            <input type="tel" placeholder="08012345678" wire:model.live.debounce.500ms="phone_number" minlength="11" maxlength="11" inputmode="numeric">
                            @error('phone_number')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Logo</label>
                            <input type="file" wire:model="newLogo" accept="image/*">
                            @error('newLogo')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                            @if($logo && !$newLogo)
                                <div class="mt-2">
                                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo Preview" class="rounded" style="height: 80px; max-width: 100%;">
                                </div>

                                @else

                                <div class="mt-2" wire:transition>
                                    <img src="{{ $newLogo->temporaryUrl() }}" alt="Logo Preview" class="rounded" style="height: 80px; max-width: 100%;">
                                </div>

                            @endif

                            <div class="mt-2" wire:loading wire:target="newLogo">
                                    Uploading...
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Description</label>
                            <textarea rows="3" placeholder="Short description about the store" wire:model.live.debounce.500ms="description" required></textarea>
                            @error('description')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Social Media Platform</label>
                            <select id="social_platform"  wire:model="platform">
                                <option value="">Select platform</option>
                                <option value="x">X</option>
                                <option value="instagram">Instagram</option>
                                <option value="youtube">YouTube</option>
                                <option value="tiktok">TikTok</option>
                                <option value="facebook">Facebook</option>
                            </select>
                            @error('platform')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Social Media Username</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" id="social_username" placeholder="yourusername" wire:model="user_name">
                                <button type="button" id="addSocialBtn" class="btn btn-outline-secondary rounded-pill px-3" wire:click="addSocialMedia" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="addSocialMedia">Add</span>  
                                    <span wire:loading wire:target="addSocialMedia">Adding...</span> 
                                </button>
                            </div>
                            @error('user_name')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror 
                        </div>

                            <div class="col-12">
                                <div id="social-badges" class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
                                    @foreach ($this->social_media as $social)
                                        <div class="social-badge" wire:key="social-{{ $loop->index }}" wire:transition>
                                            <div class="platform">
                                                <div class="platform-name">{{ $social['platform'] }}</div>
                                                <div class="username">{{ $social['user_name'] }}</div>
                                            </div>
                                        <button type="button" class="remove-btn" aria-label="Remove" wire:click="removeSocialMedia({{ $loop->index }})" wire:loading.attr="disabled">&times;</button>
                                    </div>
                                    @endforeach
                                </div>
                                @error('social_media')
                                    <div class="invalid-feedback mt-1 d-block text-center">{{ $message }}</div>
                                @enderror
                            </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Address</label>
                            <textarea rows="3" placeholder="Store address" wire:model.live.debounce.500ms="address" required></textarea>
                            @error('address')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                    @can('admin-or-super-admin')
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Status</label>
                            <div class="d-flex gap-3 align-items-center">
                                <label class="d-flex align-items-center gap-2">
                                    <input type="radio" wire:model="status" value="active">
                                    Active
                                </label>
                                <label class="d-flex align-items-center gap-2">
                                    <input type="radio" wire:model="status" value="suspended">
                                    Suspended
                                </label>
                            </div>
                            @error('status')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>    
                    @endcan


                        <div class="col-12 col-sm-7 text-center mt-4">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <button type="submit" class="fill-btn border-0" wire:loading.attr="disabled">                        
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="save">
                                                <span class="fill-btn-normal">Update Store Details</span>
                                                <span class="fill-btn-hover">Update Store Details</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="save">
                                                <span class="fill-btn-normal">Updating</span>
                                                <span class="fill-btn-hover">Updating</span>
                                            </span>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 mt-4 mt-sm-0">
                                    <a href="{{ route('stores') }}" class="fill-btn-red">
                                        <span class="fill-btn-inner">
                                            <span class="fill-btn-normal">Cancel</span>
                                            <span class="fill-btn-hover">Cancel</span>
                                        </span>
                                    </a>
                                </div>

                            </div> 
                       </div>
   

                           
                       


                    </div>

                </form>

            </div>
        </div>

</div>