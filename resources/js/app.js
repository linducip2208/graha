const themeToggle=document.querySelector('#theme-toggle');const sunSvg='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="h-4 w-4"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></svg>';const moonSvg='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="h-4 w-4"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z"/></svg>';const syncThemeIcon=()=>{if(themeToggle)themeToggle.innerHTML=document.documentElement.classList.contains('dark')?sunSvg:moonSvg};syncThemeIcon();themeToggle?.addEventListener('click',()=>{const dark=document.documentElement.classList.toggle('dark');localStorage.setItem('theme',dark?'dark':'light');syncThemeIcon();if(window.Chart){Chart.defaults.color=dark?'#94a3b8':'#334155';Chart.defaults.borderColor=dark?'#22304d':'#e2e8f0';Object.values(Chart.instances).forEach(chart=>chart.update())}});
const sidebar=document.querySelector('#admin-sidebar');const overlay=document.querySelector('#sidebar-overlay');document.querySelectorAll('[data-sidebar-open]').forEach(b=>b.addEventListener('click',()=>{sidebar?.classList.remove('-translate-x-full');overlay?.classList.remove('hidden')}));document.querySelectorAll('[data-sidebar-close]').forEach(b=>b.addEventListener('click',()=>{sidebar?.classList.add('-translate-x-full');overlay?.classList.add('hidden')}));
const toastRoot=document.querySelector('#toast-root');const showToast=(message,tone)=>{if(!message||!toastRoot)return;const el=document.createElement('div');el.className=`toast-item ${tone==='error'?'bg-red-600':'bg-slate-900'}`;el.innerHTML=`<span>${tone==='error'?'⚠️':'✅'}</span><span></span>`;el.lastChild.textContent=message;toastRoot.append(el);setTimeout(()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),400)},5000)};
showToast(document.body.dataset.flash,'success');showToast(document.body.dataset.flashError,'error');
const uiCopy={
'Inventory & Gudang':['Persediaan Material dan Operasi Gudang','Kelola material, lokasi penyimpanan, saldo, serta setiap penerimaan dan pengeluaran yang dapat ditelusuri.'],
'Master Item & Gudang':['Tambah Material, Gudang, dan Lokasi Penyimpanan','Membuat master material beserta gudang dan bin awal sebelum transaksi stok dilakukan.'],
'Posting Movement':['Catat Penerimaan atau Pengeluaran Stok','Membukukan perubahan stok ke ledger immutable; pilih jenis transaksi sesuai dokumen sumber.'],
'Fixed Asset & Depresiasi':['Aset Tetap dan Penyusutan','Kelola aset perusahaan dan bukukan penyusutan periodik secara otomatis ke jurnal.'],
'Register Aset':['Daftarkan Aset Tetap','Catat harga perolehan, nilai residu, tanggal mulai penyusutan, dan umur manfaat aset.'],
'Mapping Depresiasi':['Tentukan Akun Jurnal Penyusutan','Pilih akun beban penyusutan dan akumulasi penyusutan tanpa hard-code nomor akun.'],
'Workflow Baru':['Buat Alur Persetujuan Baru','Tentukan jenis dokumen, urutan pemeriksa, batas nilai, SLA, dan aturan keputusan.'],
'Delegation':['Delegasikan Kewenangan Sementara','Alihkan tugas persetujuan untuk periode tertentu dengan alasan dan jejak audit.'],
'My Approvals / Pending':['Dokumen yang Menunggu Keputusan Saya','Daftar pekerjaan persetujuan yang harus diperiksa sebelum melewati SLA.'],
'Workflow Configuration':['Konfigurasi Tahapan Persetujuan','Atur siapa memeriksa, urutan keputusan, quorum, dan tenggat setiap tahap.'],
'Chart of Accounts':['Daftar Akun Akuntansi','Struktur akun yang dipakai seluruh transaksi ERP untuk menghasilkan jurnal.'],
'General Ledger':['Buku Besar dan Jurnal','Tinjau jurnal posted yang seimbang dan tidak dapat diubah secara diam-diam.'],
'Input Statement':['Catat Baris Rekening Koran','Masukkan transaksi dari rekening koran untuk dicocokkan dengan penerimaan atau pembayaran.'],
'Period Closing':['Tutup Periode Akuntansi','Menutup periode setelah approval selesai dan seluruh transaksi bank telah direkonsiliasi.'],
'Bill of Material':['Susunan Bahan Produk (BOM)','Tentukan material dan kuantitas standar untuk menghasilkan satu unit produk.'],
'Production Order':['Perintah Produksi','Rencanakan jumlah yang dibuat, gudang output, dan BOM yang wajib digunakan.'],
'Production Orders':['Daftar dan Status Perintah Produksi','Pantau rencana, material yang dipakai, hasil aktual, biaya, dan status penyelesaian produksi.'],
'Equipment Register':['Daftarkan Mesin dan Peralatan','Catat kepemilikan, kategori, hour meter, dan target konsumsi bahan bakar.'],
'Vendor':['Tambah Vendor','Daftarkan pemasok yang akan digunakan pada transaksi pembelian.'],
'Draft Purchase Order':['Buat Draft Pesanan Pembelian (PO)','Siapkan pesanan pemasok sebelum dikirim melalui proses persetujuan.'],
'Purchase Orders':['Daftar dan Status Pesanan Pembelian','Pantau approval, penerimaan barang, invoice vendor, dan revisi setiap PO.'],
'Draft Progress Billing':['Buat Draft Tagihan Progres','Hitung nilai bruto, retensi, pemulihan uang muka, dan piutang bersih sebelum approval.'],
'Release Retensi':['Ajukan Pencairan Retensi','Reklasifikasikan piutang retensi menjadi piutang pelanggan setelah syarat kontrak terpenuhi.'],
'Goods Receipt \uFF0B Inventory/GRNI':['Barang Diterima: Persediaan / Utang Sementara (GRNI)','Debit persediaan dan kredit GRNI ketika barang diterima tetapi invoice vendor belum dibukukan.'],
'Matched Invoice \uFF0B GRNI/AP':['Invoice Cocok: Utang Sementara / Utang Vendor','Pindahkan GRNI menjadi utang vendor hanya setelah PO, penerimaan, dan invoice cocok.'],
'Provider Configuration':['Konfigurasi Penyedia Tanda Tangan Eksternal','Masukkan penyedia, endpoint, dan kredensial secara dinamis; secret disimpan terenkripsi.'],
'Document Versions':['Versi Dokumen yang Siap Ditandatangani','Pilih revisi dokumen yang benar; versi bertanda tangan dikunci dan tidak dapat diganti diam-diam.'],
'Risk & Opportunity':['Catat Risiko atau Peluang','Nilai kemungkinan dan dampak, tetapkan pemilik, lalu pantau tindakan pengendalian.'],
'Nonconformity (NCR)':['Catat Ketidaksesuaian (NCR)','Rekam hasil yang tidak memenuhi persyaratan agar containment dan tindakan korektif dapat dikendalikan.']};
document.querySelectorAll('h1,h2').forEach((heading)=>{const key=heading.textContent.trim();const copy=uiCopy[key];if(!copy)return;heading.textContent=copy[0];if(copy[1]&&!heading.nextElementSibling?.classList.contains('ui-purpose')){const purpose=document.createElement('p');purpose.className='ui-purpose mt-1 text-sm text-slate-500';purpose.textContent=copy[1];heading.after(purpose)}});
document.querySelectorAll('main div').forEach((workspace)=>{const forms=[...workspace.children].filter((element)=>element.tagName==='FORM'&&!element.closest('.nav-details'));if(forms.length<2)return;workspace.classList.add('workspace-tools');const toolbar=document.createElement('div');toolbar.className='workspace-toolbar no-print';forms.forEach((form,index)=>{form.classList.add('workspace-tool-panel');form.hidden=true;const button=document.createElement('button');button.type='button';button.className='workspace-tool-button';button.textContent=form.querySelector('h2')?.textContent?.trim()||form.querySelector('button')?.textContent?.trim()||`Aksi ${index+1}`;button.addEventListener('click',(ev)=>{const willOpen=form.hidden;forms.forEach((item)=>{item.hidden=true});toolbar.querySelectorAll('button').forEach((item)=>item.classList.remove('active'));if(willOpen){form.hidden=false;button.classList.add('active');if(ev.isTrusted)form.scrollIntoView({behavior:'smooth',block:'nearest'})}});toolbar.append(button)});toolbar.querySelector('button')?.click();forms[0]?.before(toolbar)});
document.querySelectorAll('details.nav-group').forEach((d)=>{const k='navgrp:'+(d.dataset.group||'');if(localStorage.getItem(k)==='0')d.open=false;d.addEventListener('toggle',()=>localStorage.setItem(k,d.open?'1':'0'))});

// ===== Adaptive Workspace Navigation (accordion): buka satu workspace menutup lainnya =====
const wsNav=document.querySelector('#workspace-nav');
if(wsNav){wsNav.querySelectorAll('details.ws-group').forEach((d)=>{d.addEventListener('toggle',()=>{if(d.open){wsNav.querySelectorAll('details.ws-group[open]').forEach((o)=>{if(o!==d)o.open=false})}})})}

// ===== Role & Permission: pencarian permission + pilih semua per modul =====
const permSearch=document.querySelector('[data-perm-search]');
if(permSearch){const groups=document.querySelectorAll('[data-perm-module]');permSearch.addEventListener('input',()=>{const q=permSearch.value.trim().toLowerCase();groups.forEach((g)=>{let visible=0;g.querySelectorAll('[data-perm-label]').forEach((label)=>{const text=(label.querySelector('[data-perm-text]')?.textContent||'').toLowerCase();const show=!q||text.includes(q);label.style.display=show?'':'none';if(show)visible++});g.style.display=visible?'':'none'})});
document.querySelectorAll('[data-select-module]').forEach((btn)=>{btn.addEventListener('click',()=>{const mod=btn.closest('[data-perm-module]');if(!mod)return;const boxes=mod.querySelectorAll('[data-perm-checkbox]');const all=[...boxes].every((b)=>b.checked);boxes.forEach((b)=>{b.checked=!all});const count=document.querySelector('[data-perm-count]');if(count)count.textContent=document.querySelectorAll('[data-perm-checkbox]:checked').length})});
document.querySelectorAll('[data-perm-checkbox]').forEach((b)=>b.addEventListener('change',()=>{const count=document.querySelector('[data-perm-count]');if(count)count.textContent=document.querySelectorAll('[data-perm-checkbox]:checked').length}))}

// ===== Form submit: kunci double-submit + loading state pada tombol =====
document.addEventListener('submit',(e)=>{
const form=e.target;
if(!(form instanceof HTMLFormElement)||form.method.toLowerCase()==='dialog')return;
const btn=form.querySelector('button[type="submit"],button:not([type])');
if(!btn||btn.dataset.loading)return;
btn.dataset.loading='1';btn.disabled=true;btn.classList.add('opacity-60','cursor-wait');
const label=btn.dataset.loadingText||'Memproses…';
btn.innerHTML='<span class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>'+label+'</span>';
});

// ===== Experience Studio: dashboard builder move up/down (urutan = urutan DOM) =====
const dashList=document.querySelector('[data-dash-list]');
if(dashList){dashList.addEventListener('click',(e)=>{const btn=e.target.closest('[data-dash-move]');if(!btn)return;e.preventDefault();const item=btn.closest('[data-dash-item]');if(!item)return;const dir=parseInt(btn.dataset.dashMove,10);const next=dir<0?item.previousElementSibling:item.nextElementSibling;if(next){dir<0?next.before(item):next.after(item)}})}

// ===== Public homepage: product proof tab preview (screenshot asli) =====
const proof=document.querySelector('[data-proof]');
if(proof){const img=proof.querySelector('[data-proof-img]');const shots={dashboard:'dashboard-redesign-v2-1440',project:'projects-portfolio-v2-1440',finance:'finance-overview-v2-1440',foundation:'foundation-control-v2-1440'};proof.querySelectorAll('[data-proof-btn]').forEach((btn)=>{btn.addEventListener('click',()=>{proof.querySelectorAll('[data-proof-btn]').forEach((b)=>b.classList.toggle('active',b===btn));const key=btn.dataset.proofBtn;if(shots[key]&&img)img.src=window.location.origin+'/marketing/screens/'+shots[key]+'.png'})})}

// ===== Drawer (create panel samping): [data-drawer-open="id"] / [data-drawer-close] =====
// A11y: simpan opener, restore focus saat tutup, aria-expanded pada opener.
let drawerOpener=null;
document.addEventListener('click',(e)=>{
const opener=e.target.closest('[data-drawer-open]');
if(opener){e.preventDefault();const drawer=document.getElementById(opener.dataset.drawerOpen);if(drawer){drawerOpener=opener;opener.setAttribute('aria-expanded','true');drawer.hidden=false;const field=drawer.querySelector('input:not([type=hidden]):not([type=file]),select,textarea');if(field)field.focus()}return}
const closer=e.target.closest('[data-drawer-close]');
if(closer){const root=closer.closest('.drawer-root');if(root){root.hidden=true;if(drawerOpener){drawerOpener.setAttribute('aria-expanded','false');drawerOpener.focus();drawerOpener=null}}}
});
document.addEventListener('keydown',(e)=>{if(e.key!=='Escape')return;if(document.querySelector('#confirm-modal:not([hidden])'))return;const open=document.querySelector('.drawer-root:not([hidden])');if(open){open.hidden=true;if(drawerOpener){drawerOpener.setAttribute('aria-expanded','false');drawerOpener.focus();drawerOpener=null}}});

// ===== Workspace UX: global search (Ctrl+K), quick create, recent views =====
const palette=document.getElementById('search-palette');const searchInput=document.getElementById('search-input');const searchResults=document.getElementById('search-results');
const openPalette=()=>{if(!palette)return;palette.hidden=false;searchInput.value='';searchResults.innerHTML='<p class="p-4 text-slate-400">Ketik minimal 2 karakter…</p>';setTimeout(()=>searchInput.focus(),30)};
const closePalette=()=>{if(palette)palette.hidden=true};
document.getElementById('global-search-trigger')?.addEventListener('click',openPalette);
document.addEventListener('keydown',(e)=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();palette&&palette.hidden?openPalette():closePalette()}if(e.key==='Escape'){closePalette();const qc=document.getElementById('quick-create-menu');if(qc)qc.hidden=true}});
if(palette)palette.addEventListener('click',(e)=>{if(e.target===palette)closePalette()});
let searchTimer=null;
searchInput?.addEventListener('input',()=>{clearTimeout(searchTimer);const q=searchInput.value.trim();if(q.length<2){searchResults.innerHTML='<p class="p-4 text-slate-400">Ketik minimal 2 karakter…</p>';return}searchTimer=setTimeout(async()=>{try{const res=await fetch('/admin/search?q='+encodeURIComponent(q),{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});if(!res.ok)throw 0;const data=await res.json();if(!data.results.length){searchResults.innerHTML='<p class="p-4 text-slate-400">Tidak ada hasil untuk “'+q.replace(/</g,'&lt;')+'”.</p>';return}searchResults.innerHTML=data.results.map(r=>'<a href="'+r.href+'" class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 hover:bg-sky-50"><span class="min-w-0"><strong class="block truncate">'+String(r.label).replace(/</g,'&lt;')+'</strong>'+(r.sublabel?'<span class="text-xs text-slate-500">'+String(r.sublabel).replace(/</g,'&lt;')+'</span>':'')+'</span><span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">'+r.type+'</span></a>').join('')}catch{searchResults.innerHTML='<p class="p-4 text-red-600">Pencarian gagal. Coba lagi.</p>'}},250)});
const qcTrigger=document.getElementById('quick-create-trigger');const qcMenu=document.getElementById('quick-create-menu');
qcTrigger?.addEventListener('click',(e)=>{e.stopPropagation();qcMenu.hidden=!qcMenu.hidden});
document.addEventListener('click',(e)=>{if(qcMenu&&!qcMenu.hidden&&!qcMenu.contains(e.target))qcMenu.hidden=true});
if(document.body.dataset.authed==='1'&&location.pathname!=='/admin/preferences/recent'){fetch('/admin/preferences/recent',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({label:(document.title||'Halaman').replace(/\s*[—|·].*$/,'').trim()||location.pathname,href:location.pathname+location.search})}).catch(()=>{})}
