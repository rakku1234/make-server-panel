<x-filament::page>
    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-8">
            <header class="mb-6 border-b pb-4 border-gray-200 dark:border-gray-700">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">サーバー情報の取り込み</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-300">
                    取り込み処理を開始する場合は、以下のボタンをクリックしてください。
                </p>
            </header>

            @if ($importResult)
                <div class="mb-6 p-4 rounded bg-green-100 dark:bg-green-900 border border-green-300 dark:border-green-600 text-green-800 dark:text-green-100">
                    {{ $importResult }}
                </div>
            @endif

            <div class="flex justify-end">
                <form wire:submit.prevent="importServersFromPelican">
                    <button type="submit" class="px-6 py-3 bg-blue-600 dark:bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-700 dark:hover:bg-blue-400 transition duration-150 ease-in-out">
                        取り込み開始
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-filament::page>
