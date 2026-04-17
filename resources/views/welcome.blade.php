<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head', ['title' => __('Order Management System')])
    </head>
    <body class="min-h-screen bg-neutral-950 font-sans text-white antialiased">
        <main class="min-h-screen overflow-hidden">
            <section class="flex min-h-screen flex-col bg-neutral-950">
                <header class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-6 py-6 lg:px-8">
                    <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="{{ __('Order Management System home') }}">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-white text-neutral-950">
                            <x-app-logo-icon class="size-6 fill-current" />
                        </span>
                        <span class="text-sm font-semibold uppercase tracking-wide text-white/90">{{ __('Order Management') }}</span>
                    </a>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-2 text-sm font-medium" aria-label="{{ __('Primary navigation') }}">
                            @auth
                                <a href="{{ route('dashboard') }}" class="rounded-md bg-white px-4 py-2 text-neutral-950 transition hover:bg-emerald-100">
                                    {{ __('Dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-md px-4 py-2 text-white/80 transition hover:text-white">
                                    {{ __('Log in') }}
                                </a>

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="rounded-md bg-white px-4 py-2 text-neutral-950 transition hover:bg-emerald-100">
                                        {{ __('Register') }}
                                    </a>
                                @endif
                            @endauth
                        </nav>
                    @endif
                </header>

                <div class="mx-auto grid w-full max-w-7xl flex-1 items-center gap-12 px-6 pb-16 pt-8 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:pb-24">
                    <div class="max-w-3xl">
                        <p class="mb-5 text-sm font-semibold uppercase tracking-wide text-emerald-300">
                            {{ __('Inventory. Orders. Reports.') }}
                        </p>

                        <h1 class="max-w-4xl text-5xl font-semibold leading-tight text-white md:text-7xl">
                            {{ __('Run product stock and order flow from one clean workspace.') }}
                        </h1>

                        <p class="mt-6 max-w-2xl text-lg leading-8 text-neutral-300">
                            {{ __('Track product quantities, confirm orders, handle cancellations, restore inventory, and review activity logs without leaving the system.') }}
                        </p>

                        <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-md bg-emerald-400 px-5 py-3 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300">
                                    {{ __('Open dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md bg-emerald-400 px-5 py-3 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300">
                                    {{ __('Start managing orders') }}
                                </a>
                            @endauth

                            <a href="#features" class="inline-flex items-center justify-center rounded-md border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/35 hover:bg-white/10">
                                {{ __('View features') }}
                            </a>
                        </div>
                    </div>

                    <div>
                        <div class="rounded-lg border border-white/10 bg-neutral-900 p-5 shadow-2xl shadow-black/30">
                            <div class="mb-6 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Today') }}</p>
                                    <h2 class="mt-1 text-2xl font-semibold">{{ __('Operations overview') }}</h2>
                                </div>

                                <span class="rounded-md bg-emerald-400/15 px-3 py-1 text-sm font-medium text-emerald-200">{{ __('Live') }}</span>
                            </div>

                            <div class="grid divide-y divide-white/10 border-y border-white/10 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                                <div class="py-4 sm:pr-4">
                                    <p class="text-sm text-neutral-400">{{ __('Products') }}</p>
                                    <p class="mt-3 text-3xl font-semibold">128</p>
                                </div>

                                <div class="py-4 sm:px-4">
                                    <p class="text-sm text-neutral-400">{{ __('Orders') }}</p>
                                    <p class="mt-3 text-3xl font-semibold">36</p>
                                </div>

                                <div class="py-4 sm:pl-4">
                                    <p class="text-sm text-neutral-400">{{ __('Revenue') }}</p>
                                    <p class="mt-3 text-3xl font-semibold">$8.4k</p>
                                </div>
                            </div>

                            <div class="mt-2 divide-y divide-white/10">
                                <div class="flex items-center justify-between gap-4 py-4">
                                    <div>
                                        <p class="font-medium">{{ __('Confirmed order') }}</p>
                                        <p class="text-sm text-neutral-400">{{ __('Stock deducted and logged') }}</p>
                                    </div>
                                    <span class="text-sm text-emerald-200">ORD-1048</span>
                                </div>

                                <div class="flex items-center justify-between gap-4 py-4">
                                    <div>
                                        <p class="font-medium">{{ __('Partial cancellation') }}</p>
                                        <p class="text-sm text-neutral-400">{{ __('Inventory restored automatically') }}</p>
                                    </div>
                                    <span class="text-sm text-amber-200">ORD-1042</span>
                                </div>

                                <div class="flex items-center justify-between gap-4 py-4">
                                    <div>
                                        <p class="font-medium">{{ __('Low stock review') }}</p>
                                        <p class="text-sm text-neutral-400">{{ __('Products needing attention') }}</p>
                                    </div>
                                    <span class="text-sm text-red-200">5 items</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="bg-white px-6 py-20 text-neutral-950 lg:px-8">
                <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.8fr_1.2fr]">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">{{ __('Built for daily operations') }}</p>
                        <h2 class="mt-4 text-3xl font-semibold leading-tight md:text-4xl">
                            {{ __('Simple tools for stock, orders, logs, and reports.') }}
                        </h2>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="border-t border-neutral-200 pt-5">
                            <h3 class="text-lg font-semibold">{{ __('Product control') }}</h3>
                            <p class="mt-3 text-neutral-600">{{ __('Add products, adjust stock, and keep current quantities easy to review.') }}</p>
                        </div>

                        <div class="border-t border-neutral-200 pt-5">
                            <h3 class="text-lg font-semibold">{{ __('Order handling') }}</h3>
                            <p class="mt-3 text-neutral-600">{{ __('Create orders, confirm stock deductions, and cancel safely when plans change.') }}</p>
                        </div>

                        <div class="border-t border-neutral-200 pt-5">
                            <h3 class="text-lg font-semibold">{{ __('Activity history') }}</h3>
                            <p class="mt-3 text-neutral-600">{{ __('Review product and order events with readable logs for every important movement.') }}</p>
                        </div>

                        <div class="border-t border-neutral-200 pt-5">
                            <h3 class="text-lg font-semibold">{{ __('Basic reporting') }}</h3>
                            <p class="mt-3 text-neutral-600">{{ __('See order totals, active revenue, cancellations, and inventory status at a glance.') }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
