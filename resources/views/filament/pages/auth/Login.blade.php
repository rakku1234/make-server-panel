<style>
    label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        transition: border-color 0.3s;
    }
    input:focus {
        border-color: #007bff;
        outline: none;
    }
    .login-button {
        width: 100%;
        padding: 10px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
</style>
<div>
    <h2 class="text-2xl mb-4" style="text-align: center;">{{ config('app.name') }}</h2>
    <h2 class="text-2xl" style="text-align: center;">ログイン</h2>
    <form wire:submit.prevent="authenticate" method="POST">
        @csrf
        <div class="form-group mb-4">
            <label for="name">ユーザー名<span style="color: red;">*</span></label>
            <input style="border-radius: 15px;" type="text" id="name" name="name" wire:model.defer="name" required class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
        </div>
        <div x-data="{ show: false }" class="form-group mb-4">
            <label for="password">パスワード<span style="color: red;">*</span></label>
            <div class="flex">
                <input :type="show ? 'text' : 'password'" id="password" name="password" wire:model.defer="password" required class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 flex-grow" style="border-radius: 15px 0 0 15px;">
                <button type="button" @click="show = !show" style="border-radius: 0 15px 15px 0;" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-l-0 border-gray-300 dark:border-gray-600 px-3 py-2">
                    <template x-if="!show">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </template>
                    <template x-if="show">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </template>
                </button>
            </div>
        </div>
        <div class="form-group mb-4">
            <label for="remember" class="inline-flex items-center">
                <input type="checkbox" id="remember" name="remember" wire:model.defer="remember" class="mr-2 w-5 h-5 appearance-none border border-gray-300 rounded checked:bg-blue-500 checked:border-blue-500 focus:outline-none">
                &ensp;ログイン状態を保持する
            </label>
        </div>
        <div class="form-group mb-4">
            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.sitekey') }}"></div>
        </div>
        <button class="login-button" type="submit">ログイン</button>
    </form>
</div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
