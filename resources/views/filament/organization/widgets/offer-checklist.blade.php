<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Ce poți publica</x-slot>

        <x-slot name="description">
            @if ($published)
                Ce ești se citește din ce ai publicat — nu ai de bifat nimic.
            @else
                Nu ai publicat nimic încă, deci nu ai nici pagină publică. Începe
                cu oricare dintre ele.
            @endif
        </x-slot>

        @if ($address)
            <a
                href="{{ $address['url'] }}"
                class="flex items-center gap-4 rounded-xl border border-gray-200 p-4 transition hover:border-primary-500 dark:border-white/10 dark:hover:border-primary-500"
            >
                <span class="text-2xl">{{ $address['icon'] }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $address['label'] }}
                    </span>
                    <span class="block text-sm text-gray-500 dark:text-gray-400">
                        {{ $address['help'] }}
                    </span>
                </span>
                <span class="shrink-0 text-sm font-medium whitespace-nowrap">
                    @if ($address['count'] > 0)
                        <span class="text-primary-600 dark:text-primary-400">
                            {{ $address['count'] }} {{ $address['unit'] }}
                        </span>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">adaugă →</span>
                    @endif
                </span>
            </a>
        @endif

        <div class="mt-3 grid gap-3 md:grid-cols-3">
            @foreach ($offers as $offer)
                <a
                    href="{{ $offer['url'] }}"
                    @class([
                        'flex flex-col gap-2 rounded-xl border p-4 transition hover:border-primary-500',
                        'border-primary-500/60 bg-primary-50/50 dark:bg-primary-500/5' => $offer['done'],
                        'border-gray-200 dark:border-white/10' => ! $offer['done'],
                    ])
                >
                    <span class="text-2xl">{{ $offer['icon'] }}</span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $offer['label'] }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $offer['help'] }}
                    </span>
                    <span class="mt-auto pt-1 text-sm font-medium">
                        @if ($offer['done'])
                            <span class="text-primary-600 dark:text-primary-400">
                                {{ $offer['count'] }} {{ $offer['unit'] }}
                            </span>
                        @else
                            <span class="text-gray-500 dark:text-gray-400">adaugă →</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
