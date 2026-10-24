@props(['status'])
<span class="badge badge-{{ $status }}">{{ ['paid' => 'Paid', 'partial' => 'Part paid', 'unpaid' => 'Unpaid'][$status] ?? ucfirst($status) }}</span>
