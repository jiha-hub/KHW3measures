<?php
// Toss 스타일 디자인 토큰 — 모든 페이지 공통 적용
// 각 페이지의 <style> 및 pwa_head.php 뒤, </head> 직전에 include할 것
// (CSS 캐스케이드상 나중에 선언되므로 동일 변수/클래스는 이 파일이 최종 적용됨)
?>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@latest/dist/web/static/pretendard.css">
<style>
:root{
  --bg:#f7f8fa;
  --card:#fff;
  --primary:#3182f6;
  --primary-dark:#1b64da;
  --text:#191f28;
  --muted:#8b95a1;
  --border:#e5e8eb;
  --radius:20px;
  --shadow:0 2px 16px rgba(15,23,42,.06);
}
html,body{font-family:'Pretendard','Apple SD Gothic Neo','Noto Sans KR',sans-serif;}

/* 버튼: 더 둥글게 + 눌림 마이크로 인터랙션 */
.btn,.btn-login,.btn-primary,.btn-secondary,.btn-new,.btn-hist,.btn-print,.btn-ghost{
  border-radius:16px;
  transition:transform .15s ease,background-color .2s ease,filter .2s ease,border-color .2s ease,box-shadow .2s ease;
}
.btn:active,.btn-login:active{transform:scale(.96);}

/* 입력 필드 */
input[type=text],input[type=tel],input[type=password],input[type=date],select,textarea{
  border-radius:14px;
}

/* 카드형 선택 요소 */
.scale-option,.q-option-btn,.gender-radio,.c-choice,.time-input,.num-field,
.info-bar,.consent-box,.q-card,.alert-error,.alert-success,.alert{
  border-radius:14px;
}
.header-nav a{border-radius:10px;}

/* 은은한 등장 애니메이션 */
@keyframes fadeSlideUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
@keyframes popPulse{0%{transform:scale(1);}45%{transform:scale(1.05);}100%{transform:scale(1);}}
.card{animation:fadeSlideUp .35s ease both;}
.complete-screen.active{animation:fadeSlideUp .4s ease both;}
.scale-option.selected,.q-option-btn.selected,.gender-radio.selected{animation:popPulse .22s ease;}

/* 제작 크레딧 */
.site-credit{position:fixed;left:0;right:0;bottom:4px;text-align:center;font-size:.66rem;color:var(--muted,#9ca3af);opacity:.5;letter-spacing:.04em;font-family:inherit;user-select:none;pointer-events:none;z-index:1;}
@media print{.site-credit{display:none !important;}}
</style>
<script>
// 점수 등 숫자를 부드럽게 카운트업 표시
function tossCountUp(el, target, opts) {
  if (!el) return;
  opts = opts || {};
  var duration = opts.duration || 600;
  var suffix = opts.suffix || '';
  var startTime = null;
  function step(ts) {
    if (!startTime) startTime = ts;
    var progress = Math.min((ts - startTime) / duration, 1);
    var eased = 1 - Math.pow(1 - progress, 3);
    var val = Math.round(target * eased);
    el.textContent = val + suffix;
    if (progress < 1) requestAnimationFrame(step);
    else el.textContent = target + suffix;
  }
  requestAnimationFrame(step);
}

// 사이트 최하단 제작 크레딧
(function(){
  function addCredit(){
    if (document.querySelector('.site-credit')) return;
    var el = document.createElement('div');
    el.className = 'site-credit';
    el.textContent = 'Made by Jeong Jiha';
    document.body.appendChild(el);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addCredit);
  else addCredit();
})();
</script>
