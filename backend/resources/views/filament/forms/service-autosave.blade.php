<div
    wire:poll.20s="autosaveDraft"
    style="display:flex;align-items:center;gap:.55rem;padding:.7rem .9rem;border:1px solid #d1fae5;border-radius:.75rem;background:#ecfdf5;color:#047857"
>
    <span aria-hidden="true" style="width:.55rem;height:.55rem;border-radius:999px;background:#10b981"></span>
    <span style="font-size:.875rem;font-weight:600">{{ $this->autosaveMessage }}</span>
</div>
