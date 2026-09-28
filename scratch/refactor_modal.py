import re
import sys

file_path = r"d:\WebServer\www\simba\resources\views\livewire\siswa\index.blade.php"

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update Livewire Component Methods
content = content.replace(
    "$this->action = 'create';",
    "$this->action = 'create';\n        $this->dispatch('show-modal-siswa');"
)
content = content.replace(
    "$this->action = 'show';",
    "$this->action = 'show';\n        $this->dispatch('show-modal-siswa');"
)
content = content.replace(
    "$this->newTags = [];\n    }",
    "$this->newTags = [];\n        $this->dispatch('show-modal-siswa');\n    }"
)
content = content.replace(
    "$this->resetForm();\n        $this->dispatch('toast', message: 'Siswa berhasil ditambahkan', type: 'success');",
    "$this->resetForm();\n        $this->dispatch('close-modal', id: 'modalSiswaForm');\n        $this->dispatch('toast', message: 'Siswa berhasil ditambahkan', type: 'success');"
)
content = content.replace(
    "$this->resetForm();\n        $this->dispatch('toast', message: 'Siswa berhasil diperbarui', type: 'success');",
    "$this->resetForm();\n        $this->dispatch('close-modal', id: 'modalSiswaForm');\n        $this->dispatch('toast', message: 'Siswa berhasil diperbarui', type: 'success');"
)

# 2. Update TABS AREA
content = content.replace(
    """<div x-data="{ activeTab: 'filter' }" x-init="$watch('$wire.action', value => { if(value === 'edit' || value === 'create' || value === 'show') activeTab = 'form' })">""",
    """<div x-data="{ activeTab: 'filter' }">"""
)

# 3. Extract FORM AREA and remove it from tab-content
form_start_marker = "{{-- FORM AREA --}}"
filter_start_marker = "{{-- FILTER AREA --}}"

start_idx = content.find(form_start_marker)
end_idx = content.find(filter_start_marker)

if start_idx != -1 and end_idx != -1:
    form_html = content[start_idx:end_idx]
    
    # Remove it from the original place
    content = content[:start_idx] + content[end_idx:]
    
    # Build the modal HTML
    # We will grab the inner parts, but let's just dump the form_html inside the modal body
    # We can remove the `<div x-show="activeTab === 'form'" style="display: none;">` wrapper.
    # Actually, simpler: just wrap the whole `form_html` in a modal and remove the `x-show` line manually.
    
    # Let's clean the form_html
    form_html = re.sub(r'<div x-show="activeTab === \'form\'" style="display: none;">\s*', '', form_html, count=1)
    # the last </div> before FILTER AREA needs to be removed as well, we'll do this by stripping right and removing </div>
    form_html = form_html.rstrip()
    if form_html.endswith('</div>'):
        form_html = form_html[:-6]
        
    # Replace Batal/Tutup buttons to also dismiss modal
    form_html = form_html.replace(
        """<button type="button" wire:click="resetForm" class="btn btn-secondary">BATAL</button>""",
        """<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" wire:click="resetForm">BATAL</button>"""
    )
    form_html = form_html.replace(
        """<button wire:click="resetForm"\n                                                class="btn btn-sm btn-secondary">Tutup</button>""",
        """<button type="button" data-bs-dismiss="modal" wire:click="resetForm"\n                                                class="btn btn-sm btn-secondary">Tutup</button>"""
    )
    # Remove headers as they go to modal header
    form_html = form_html.replace(
        """<h6 class="fw-bold text-primary mb-3">
                                    <i class="bi {{ $action == 'edit' ? 'bi-pencil-square' : 'bi-plus-circle' }} me-1"></i>
                                    {{ $action == 'edit' ? 'Edit' : 'Tambah' }} Data Siswa
                                </h6>""",
        ""
    )
    form_html = form_html.replace(
        """<h6 class="fw-bold text-primary mb-3"><i class="bi bi-eye me-1"></i> Detail Siswa</h6>""",
        ""
    )

    # Empty state button is useless now since button is at top nav
    empty_state_str = """@else
                            <div class="d-flex justify-content-between align-items-center">
                                
                            </div>
                        @endif"""
    form_html = form_html.replace(empty_state_str, "@endif")

    modal_html = f"""
    {{-- MODAL SISWA FORM --}}
    <div wire:ignore.self class="modal fade" id="modalSiswaForm" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        @if($action == 'edit') <i class="bi bi-pencil-square me-2"></i> Edit Data Siswa
                        @elseif($action == 'create') <i class="bi bi-plus-circle me-2"></i> Tambah Data Siswa
                        @else <i class="bi bi-eye me-2"></i> Detail Siswa @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" wire:click="resetForm"></button>
                </div>
                <div class="modal-body">
                    {form_html.strip()}
                </div>
            </div>
        </div>
    </div>
"""

    # Insert modal_html before MODAL BULK UPDATE
    bulk_update_marker = "{{-- MODAL BULK UPDATE --}}"
    content = content.replace(bulk_update_marker, modal_html + "\n    " + bulk_update_marker)

# 4. Add Javascript listener
js_target = """$wire.on('show-modal-delete', () => {
                new bootstrap.Modal(document.getElementById('modalBulkDelete')).show();
            });"""
js_replacement = js_target + """\n\n            $wire.on('show-modal-siswa', () => {
                new bootstrap.Modal(document.getElementById('modalSiswaForm')).show();
            });"""
content = content.replace(js_target, js_replacement)


with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Migration completed successfully.")
