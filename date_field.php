<?php
// =============================================================
// 검사일/날짜 입력 공용 컴포넌트
//   - 숫자패드로 직접 입력(8자리 → YYYY-MM-DD 자동 하이픈) + 달력(📅) 선택 둘 다 지원
//   - renderDateField(name, value, id, placeholder, extraAttr) : 입력 위젯 출력
//   - dateFieldAssets() : CSS+JS (페이지당 1회만 출력됨)
// =============================================================

if (!function_exists('renderDateField')) {
    function renderDateField(string $name = '', string $value = '', string $id = '', string $placeholder = 'YYYY-MM-DD', string $extraAttr = ''): void {
        $id = $id !== '' ? $id : ($name !== '' ? $name . '_df' : 'df_' . substr(md5(uniqid('', true)), 0, 6));
        $nameAttr = $name !== '' ? ' name="' . htmlspecialchars($name) . '"' : '';
        ?>
<div class="datefield">
  <input type="text"<?= $nameAttr ?> id="<?= htmlspecialchars($id) ?>" class="df-text"
         inputmode="numeric" maxlength="10" autocomplete="off"
         placeholder="<?= htmlspecialchars($placeholder) ?>" value="<?= htmlspecialchars($value) ?>" <?= $extraAttr ?>>
  <button type="button" class="df-cal-btn" tabindex="-1" aria-label="달력에서 날짜 선택" title="달력에서 선택">📅</button>
  <input type="date" class="df-cal" tabindex="-1" aria-hidden="true">
</div>
        <?php
    }
}

if (!function_exists('dateFieldAssets')) {
    function dateFieldAssets(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        ?>
<style>
.datefield{position:relative;display:flex;align-items:center;width:100%;}
.datefield .df-text{width:100%;padding-right:42px !important;font-variant-numeric:tabular-nums;letter-spacing:.02em;}
.datefield .df-cal-btn{position:absolute;right:4px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.15rem;line-height:1;padding:6px;border-radius:8px;}
.datefield .df-cal-btn:hover{background:rgba(0,0,0,.06);}
.datefield .df-cal{position:absolute;right:8px;bottom:0;width:1px;height:1px;padding:0;border:0;opacity:0;pointer-events:none;}
</style>
<script>
(function(){
  function fmt(v){
    var d=(v||'').replace(/[^0-9]/g,'').slice(0,8), f=d;
    if(d.length>=5) f=d.slice(0,4)+'-'+d.slice(4);
    if(d.length>=7) f=d.slice(0,4)+'-'+d.slice(4,6)+'-'+d.slice(6);
    return f;
  }
  function wire(w){
    if(w.dataset.dfInit) return; w.dataset.dfInit='1';
    var t=w.querySelector('.df-text'), cal=w.querySelector('.df-cal'), btn=w.querySelector('.df-cal-btn');
    if(!t||!cal||!btn) return;
    t.addEventListener('input', function(){ t.value=fmt(t.value); });
    btn.addEventListener('click', function(){
      if(/^\d{4}-\d{2}-\d{2}$/.test(t.value)) cal.value=t.value;
      if(typeof cal.showPicker==='function'){ try{ cal.showPicker(); return; }catch(e){} }
      cal.focus(); cal.click();  // 폴백
    });
    cal.addEventListener('change', function(){
      if(cal.value){ t.value=cal.value; t.dispatchEvent(new Event('input',{bubbles:true})); t.dispatchEvent(new Event('change',{bubbles:true})); }
    });
  }
  window.initDateFields=function(){ document.querySelectorAll('.datefield').forEach(wire); };
  if(document.readyState!=='loading') window.initDateFields();
  else document.addEventListener('DOMContentLoaded', window.initDateFields);
})();
</script>
        <?php
    }
}
