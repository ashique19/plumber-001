<?php

namespace App\Livewire\Admin;

use App\Models\StaffUser;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Support\EnabledLocales;
use App\Support\LocaleCookie;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class StaffProfile extends Component
{
    use DispatchesToasts;
    use ValidatesFieldsLive;
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $locale = '';

    public function mount(): void
    {
        /** @var StaffUser $user */
        $user = Auth::guard('staff')->user();
        $this->name = $user->name;
        $this->email = (string) $user->email;
        $this->locale = EnabledLocales::resolve(
            $user->locale ?? session('locale') ?? config('locales.default', 'bn'),
        );
    }

    public function save(): void
    {
        /** @var StaffUser $user */
        $user = Auth::guard('staff')->user();

        $validated = $this->validate(
            $this->profileValidationRules($user->id),
            [],
            $this->profileValidationAttributes(),
        );

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'locale' => $validated['locale'],
        ]);

        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $user->save();

        session()->put('locale', $validated['locale']);
        app()->setLocale($validated['locale']);
        Cookie::queue(Cookie::forever(LocaleCookie::NAME, $validated['locale']));

        $this->password = '';
        $this->password_confirmation = '';
        $this->toastSuccess(__('profile.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        /** @var StaffUser $user */
        $user = Auth::guard('staff')->user();

        return $this->profileValidationRules($user->id);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function profileValidationRules(int $userId): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('staff_users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'confirmed', PasswordRule::defaults()],
            'locale' => ['required', Rule::in(EnabledLocales::codes())],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function profileValidationAttributes(): array
    {
        return [
            'name' => __('profile.name'),
            'email' => __('profile.email'),
            'password' => __('profile.password'),
            'password_confirmation' => __('profile.password_confirm'),
            'locale' => __('profile.language'),
        ];
    }

    public function render(): View
    {
        $localeOptions = collect(EnabledLocales::codes())
            ->mapWithKeys(fn (string $code) => [
                $code => (string) config("locales.supported.{$code}.native", strtoupper($code)),
            ])
            ->all();

        return view('livewire.admin.staff-profile', [
            'localeOptions' => $localeOptions,
        ])->layout('layouts.pos', ['pageTitle' => __('profile.title')]);
    }
}