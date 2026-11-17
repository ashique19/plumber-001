<?php

namespace App\Livewire\Admin;

use App\Models\StaffUser;
use App\Livewire\Concerns\DispatchesToasts;
use App\Support\EnabledLocales;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class StaffProfile extends Component
{
    use DispatchesToasts;
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
            session('locale') ?? config('locales.default', 'bn'),
        );
    }

    public function save(): void
    {
        /** @var StaffUser $user */
        $user = Auth::guard('staff')->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('staff_users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
            'locale' => ['required', Rule::in(EnabledLocales::codes())],
        ], [], [
            'name' => __('profile.name'),
            'email' => __('profile.email'),
            'password' => __('profile.password'),
            'password_confirmation' => __('profile.password_confirm'),
            'locale' => __('profile.language'),
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $user->save();

        session()->put('locale', $validated['locale']);
        app()->setLocale($validated['locale']);

        $this->password = '';
        $this->password_confirmation = '';
        $this->toastSuccess(__('profile.saved'));
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