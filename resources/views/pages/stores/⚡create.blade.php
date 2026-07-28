<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Models\User;
use App\Models\Store;
use App\Models\Social;
use Illuminate\Support\Facades\Mail;
use App\Mail\StoreRegistrationEmail;

new class extends Component
{
    use WithFileUploads;
    #[Title('Add a Store')]

    public string $name = ' ';
    public string $email = ' ';
    public string $store_name = ' ';
    public string $phone_number = ' ';
    public $logo;
    public string $description = ' ';
    public string $platform = ' ';
    public string $user_name = ' ';
    public array $social_media = [];

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
                'required',
                'email',
                'unique:users,email',
            ],
            'store_name' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[^<>]*$/',
            ],
            'phone_number' => [
                'required',
                'string',
                'size:11',
            ],
             'logo' => [
                'required',
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
            'social_media' => [
                'required',
                'array',
                'min:1'
            ],

    ];
    }

    public function addSocialMedia()
    {
        // Validate the platform and username fields
        $this->validate([
            'platform' => 'required|string',
            'user_name' => 'required|string',
        ]);

        // Add the social media platform and username to the socialMedia array
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
        $this->validate($this->rules());
        $password = Str::random(8);
        $userData = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $password,
            'role' => 'store'
        ]);

        $storeData = Store::create([
            'user_id' => $userData->id,
            'name' => $this->store_name,
            'phone_number' => $this->phone_number,
            'logo' => $this->logo,
            'description' => $this->description
        ]);

        $socials = new Social();
        if(count($this->social_media) == 1){
            $socials->store_id = $storeData->id;
            $socials->platform = $this->social_media['platform'];
            $socials->user_name = $this->social_media['user_name'];
            $socials->save();
        }
        else {
            foreach($this->social_media as $index => $social){
                $socials->store_id = $storeData->id;
                $socials->platform = $social['platform'];
                $socials->user_name = $social['user_name'];
                $socials->save();
            }
        }

        Mail::to($this->email)->send(new StoreRegistrationEmail($password, $this->name, $this->store_name));
        session()->flash('store-registration-successful','Store registered successfully');
        return $this->redirect(route('stores'), navigate:true);

    }
    
};
?>
<div class="dashboard-content">
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1 h5">Add New Store</h4>
            <p class="text-muted small mb-0">Provide store details to create a new merchant store.</p>
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
                            <input type="text" placeholder="My Awesome Store" required wire:model.live.debounce.500ms="store_name">
                            @error('store_name')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Contact Email</label>
                            <input type="email" placeholder="owner@example.com" required wire:model.live.debounce.500ms="email">
                            @error('email')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Phone Number</label>
                            <input type="text" placeholder="+234 800 000 0000" wire:model.live.debounce.500ms="phone_number">
                            @error('phone_number')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Logo</label>
                            <input type="file" wire:model="logo" accept="image/*" required>
                            @error('logo')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                            @if($logo)
                                <div class="mt-2">
                                    <img src="{{ $logo->temporaryUrl() }}" alt="Logo Preview" class="rounded" style="height: 80px; max-width: 100%;">
                                </div>
                            @endif

                            <div class="mt-2" wire:loading wire:target="logo">
                                    Uploading...
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Description</label>
                            <textarea rows="3" placeholder="Short description about the store" wire:model.live.debounce.500ms="description"></textarea>
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
                                <div id="social-badges" class="d-flex flex-wrap gap-2 mt-3">
                                    <!-- Static example badge (CSS-only preview) -->
                                    @foreach ($this->social_media as $social)
                                        <div class="social-badge">
                                            <div class="platform">
                                                <div class="platform-name">{{ $social['platform'] }}</div>
                                                <div class="username">{{ $social['user_name'] }}</div>
                                            </div>
                                        <button type="button" class="remove-btn" aria-label="Remove" wire:click="removeSocialMedia({{ $loop->index }})" wire:loading.attr="disabled">&times;</button>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                        <div class="col-12 col-sm-6">
                            <div class="row">
                                <div class="col-12 col-sm-6 text-center">
                                    <button type="submit" class="fill-btn border-0">                        
                                            <span class="fill-btn-inner">
                                                <span class="fill-btn-normal">Add Store</span>
                                                <span class="fill-btn-hover">Add Store</span>
                                            </span>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 text-center mt-4 mt-sm-0">
                                    <a href="#" class="fill-btn-red">
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