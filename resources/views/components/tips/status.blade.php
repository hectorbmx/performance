@props(['status'])
<span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">{{ \App\Enums\TipStatus::labels()[$status->value] }}</span>
