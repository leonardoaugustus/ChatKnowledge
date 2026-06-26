{{-- Local-only design-system showcase. Renders every shared partial with sample
     data so the UI kit can be reviewed in isolation. Not for production. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'UI Kit'])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-white">
        <div class="mx-auto flex max-w-shell flex-col gap-12 px-6 py-10">
            <x-page-header title="UI Kit" description="Showcase local dos componentes compartilhados (Phase 1.3).">
                <x-slot:actions>
                    <flux:badge color="amber">local only</flux:badge>
                </x-slot:actions>
            </x-page-header>

            {{-- Flash messages --}}
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">Flash messages</flux:heading>
                <div class="flex flex-col gap-2">
                    @foreach (['success' => 'Operação concluída com sucesso.', 'error' => 'Algo deu errado.', 'warning' => 'Atenção: revise os dados.', 'info' => 'Informação útil para você.'] as $variant => $message)
                        @php
                            $variants = [
                                'success' => ['color' => 'green', 'icon' => 'check-circle'],
                                'error' => ['color' => 'red', 'icon' => 'x-circle'],
                                'warning' => ['color' => 'amber', 'icon' => 'exclamation-triangle'],
                                'info' => ['color' => 'blue', 'icon' => 'information-circle'],
                            ][$variant];
                        @endphp
                        <flux:callout :color="$variants['color']" :icon="$variants['icon']" inline>
                            <flux:callout.text>{{ $message }}</flux:callout.text>
                        </flux:callout>
                    @endforeach
                </div>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    Em uma página real use <code class="font-mono">&lt;x-flash /&gt;</code> com <code class="font-mono">session()-&gt;flash('success', '...')</code>.
                </flux:text>
            </section>

            {{-- Status badges --}}
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">Status badges</flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach (App\Enums\AgentStatus::cases() as $status)
                        <x-status-badge :status="$status" />
                    @endforeach
                    @foreach (App\Enums\DocumentStatus::cases() as $status)
                        <x-status-badge :status="$status" />
                    @endforeach
                    @foreach (App\Enums\CurationStatus::cases() as $status)
                        <x-status-badge :status="$status" />
                    @endforeach
                    @foreach (App\Enums\Role::cases() as $role)
                        <x-status-badge :status="$role" />
                    @endforeach
                </div>
            </section>

            {{-- Bar widget --}}
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">Bar widget</flux:heading>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-bar-widget
                        title="Perguntas por agente"
                        :items="[
                            ['label' => 'Suporte', 'value' => 128, 'color' => 'brand'],
                            ['label' => 'Vendas', 'value' => 76, 'color' => 'green'],
                            ['label' => 'RH', 'value' => 42, 'color' => 'cyan'],
                            ['label' => 'Financeiro', 'value' => 18, 'color' => 'amber'],
                        ]"
                    />
                    <x-bar-widget title="Sem dados" :items="[]" />
                </div>
            </section>

            {{-- Plan usage --}}
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">Plan usage</flux:heading>
                <div class="grid max-w-md gap-4 sm:grid-cols-2">
                    <x-plan-usage plan="Pro" label="perguntas" :used="640" :limit="1000" />
                    <x-plan-usage plan="Pro" label="perguntas" :used="930" :limit="1000" />
                    <x-plan-usage plan="Free" />
                </div>
            </section>

            {{-- Empty state --}}
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">Empty state</flux:heading>
                <x-empty-state
                    icon="cpu-chip"
                    heading="Nenhum agente ainda"
                    description="Crie seu primeiro agente para começar a treinar a base de conhecimento."
                >
                    <x-slot:actions>
                        <flux:button variant="primary" icon="plus">Novo agente</flux:button>
                    </x-slot:actions>
                </x-empty-state>
            </section>

            {{-- Confirmation modal --}}
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">Confirmation modal</flux:heading>
                <div>
                    <flux:modal.trigger name="ui-kit-confirm">
                        <flux:button variant="danger" icon="trash">Excluir agente</flux:button>
                    </flux:modal.trigger>
                </div>
                <x-confirm-modal
                    name="ui-kit-confirm"
                    title="Excluir agente?"
                    description="Esta ação não pode ser desfeita. O vector store do agente também será removido."
                    confirm-label="Excluir"
                    variant="danger"
                />
            </section>
        </div>

        @fluxScripts
    </body>
</html>
