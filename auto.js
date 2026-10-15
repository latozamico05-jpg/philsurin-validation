document.addEventListener('DOMContentLoaded', function () {
  const notice=document.querySelector('.ok,.error'); if(notice){notice.style.position='fixed';notice.style.right='22px';notice.style.top='18px';notice.style.zIndex='20';notice.style.padding='13px 18px';notice.style.borderRadius='9px';notice.style.boxShadow='0 8px 22px rgba(0,0,0,.15)';setTimeout(function(){notice.style.opacity='0';notice.style.transition='opacity .4s';},4500);}
  const head=document.querySelector('header'); if(head&&!head.querySelector('.report-link')){const a=document.createElement('a');a.className='report-link';a.href='report.php';a.textContent='REPORT';a.style.cssText='display:inline-block;background:#07865f;color:#fff!important;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:800;letter-spacing:.3px;margin-left:14px;box-shadow:0 4px 10px rgba(7,134,95,.2)';head.appendChild(a);const l=document.createElement('a');l.className='report-link';l.href='locations.php';l.textContent='TRIAL LOCATIONS';l.style.cssText=a.style.cssText;l.style.background='#315b78';head.appendChild(l);const au=document.createElement('a');au.className='report-link';au.href='audit.php';au.textContent='AUDIT TRAIL';au.style.cssText=a.style.cssText;au.style.background='#536278';head.appendChild(au);}
  if(document.querySelector('.report')){const s=document.createElement('style');s.textContent='body{font:11px Inter,Segoe UI,Arial;background:#f4f7f9;color:#15263d}.toolbar{background:#fff;border:1px solid #e5eaf0;border-radius:10px;box-shadow:0 5px 18px rgba(28,48,72,.07);padding:10px;margin-bottom:10px}.toolbar button,.toolbar a{background:#07865f;border-radius:6px;padding:8px 12px;color:#fff;text-decoration:none;border:0;font-weight:700}.report{background:#fff;border-radius:2px;overflow:hidden;box-shadow:0 5px 18px rgba(28,48,72,.07)}.report th{background:#edf7f2;color:#155b48;font-weight:700}.report td,.report th{border-color:#cfd9e2}.report .stage{color:#07865f;background:#dff2ea}.meta{background:#fff;border:1px solid #dce4ea;border-radius:8px;padding:9px;margin:8px 0;color:#536278;display:flex;gap:28px}.report .ave{background:#fff8e9}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin:8px 0}.summary div{background:#fff;border:1px solid #dce4ea;border-radius:8px;padding:7px 10px;min-height:38px}.summary b{display:block;color:#718095;font-size:8px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}.summary span{font-weight:700;color:#24354a}@media print{.summary{display:none}}';document.head.appendChild(s);const meta=document.querySelector('.meta');if(meta&&!document.querySelector('.summary')){const box=document.createElement('div');box.className='summary';const text=meta.innerText.split(/\s{2,}/);['LOCATION ID','LOCATION OF PROJECT','DATE PLANTED','DR / DP','CHECK / CONTROL','REPLICATIONS'].forEach(function(k,i){const d=document.createElement('div');d.innerHTML='<b>'+k+'</b><span>'+((text[i]||'').replace(k+':','').trim()||'—')+'</span>';box.appendChild(d)});meta.parentNode.insertBefore(box,meta);}}
  const form = document.querySelector('form');
  const id = document.querySelector('input[name="location_id"]');
  if (!form || !id) return;
  form.addEventListener('submit',function(e){var size=form.elements['location_size'];if(size&&!size.value.trim()){e.preventDefault();size.style.borderColor='#c62828';size.focus();var n=document.createElement('div');n.className='error';n.textContent='Please enter the Size of Location before saving.';document.body.appendChild(n);n.style.cssText='position:fixed;right:22px;top:18px;z-index:20;padding:13px 18px;border-radius:9px;box-shadow:0 8px 22px rgba(0,0,0,.15)';setTimeout(function(){n.remove();},4500);}});
  id.readOnly = true;
  id.placeholder = 'Generated automatically';
  id.value = 'Generating...';
  const sourceNames = ['project_location','date_planted','dr_dp','control_name','location_size'];
  async function updateId() {
    const key = sourceNames.map(function (n) { const e=form.elements[n]; return e ? e.value.trim() : ''; }).join('|');
    if (!key.replace(/\|/g, '')) { id.value = ''; return; }
    const bytes = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(key));
    const hex = Array.from(new Uint8Array(bytes)).map(function (b) { return b.toString(16).padStart(2,'0'); }).join('');
    id.value = 'LOC-' + hex.slice(0, 12).toUpperCase();
  }
  sourceNames.forEach(function (n) { if (form.elements[n]) form.elements[n].addEventListener('input', updateId); });
  updateId();
  const save = document.querySelector('.save');
  if (save) { save.style.clear = 'both'; save.style.display = 'block'; save.style.margin = '32px 0 0 auto'; }
});
