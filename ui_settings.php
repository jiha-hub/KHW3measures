<?php
// UI 크기 조절 공통 컴포넌트
// 모든 페이지 </body> 바로 앞에 include
?>
<style>
/* zoom 기반 전체 크기 조절 */
body.size-1 { zoom: 0.85; }
body.size-2 { zoom: 0.92; }
body.size-3 { zoom: 1.00; }
body.size-4 { zoom: 1.10; }
body.size-5 { zoom: 1.20; }

/* 슬라이더 패널 */
.ui-size-panel {
  position: fixed;
  bottom: 136px;
  right: 14px;
  background: #fff;
  border: 1.5px solid #dce3ef;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  z-index: 1001;
  display: none;
  width: 210px;
}
.ui-size-panel.open { display: block; }
.panel-title {
  font-size: .8rem;
  font-weight: 700;
  color: #6b7a99;
  margin-bottom: 12px;
  text-align: center;
  font-family: 'Apple SD Gothic Neo','Noto Sans KR',sans-serif;
}
.size-track {
  display: flex;
  justify-content: space-between;
  font-size: .7rem;
  color: #aaa;
  margin-bottom: 4px;
  font-family: 'Apple SD Gothic Neo','Noto Sans KR',sans-serif;
}
#sizeSlider {
  width: 100%;
  accent-color: #3b6cb7;
  cursor: pointer;
  height: 6px;
}
.size-preview {
  text-align: center;
  margin-top: 10px;
  font-size: .82rem;
  color: #3b6cb7;
  font-weight: 700;
  font-family: 'Apple SD Gothic Neo','Noto Sans KR',sans-serif;
}
.size-dots {
  display: flex;
  justify-content: space-between;
  padding: 0 2px;
  margin-top: 6px;
}
.size-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #dce3ef;
  cursor: pointer;
  transition: background .2s;
}
.size-dot.active { background: #3b6cb7; }

/* 크기 버튼 */
.size-fab {
  position: fixed;
  bottom: 84px;
  right: 20px;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #3b6cb7;
  color: #fff;
  border: none;
  font-size: 1.15rem;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(59,108,183,.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  transition: all .2s;
  font-family: inherit;
}
.size-fab:hover { background: #2d549a; transform: scale(1.1); }
</style>

<!-- 크기 조절 패널 -->
<div class="ui-size-panel" id="uiSizePanel">
  <div class="panel-title">🔡 화면 크기 조절</div>
  <div class="size-track"><span>작게</span><span>기본</span><span>크게</span></div>
  <input type="range" id="sizeSlider" min="1" max="5" value="3" step="1">
  <div class="size-dots" id="sizeDots">
    <div class="size-dot" onclick="applySize(1)"></div>
    <div class="size-dot" onclick="applySize(2)"></div>
    <div class="size-dot active" onclick="applySize(3)"></div>
    <div class="size-dot" onclick="applySize(4)"></div>
    <div class="size-dot" onclick="applySize(5)"></div>
  </div>
  <div class="size-preview" id="sizePreview">기본 (100%)</div>
</div>
<button class="size-fab" id="sizeFab" onclick="toggleSizePanel()" title="화면 크기 조절">🔡</button>

<script>
const _sizeLabels = ['', '작게 (85%)', '약간 작게 (92%)', '기본 (100%)', '약간 크게 (110%)', '크게 (120%)'];

function applySize(val) {
  val = Math.max(1, Math.min(5, parseInt(val)));
  for (let i = 1; i <= 5; i++) document.body.classList.remove('size-' + i);
  document.body.classList.add('size-' + val);
  document.getElementById('sizePreview').textContent = _sizeLabels[val];
  document.getElementById('sizeSlider').value = val;
  // 점 업데이트
  document.querySelectorAll('.size-dot').forEach((d, i) => {
    d.classList.toggle('active', i + 1 === val);
  });
  try { localStorage.setItem('ui_size', val); } catch(e) {}
}

function toggleSizePanel() {
  document.getElementById('uiSizePanel').classList.toggle('open');
}

document.getElementById('sizeSlider').addEventListener('input', function() {
  applySize(parseInt(this.value));
});

// 외부 클릭 시 닫기
document.addEventListener('click', function(e) {
  const panel = document.getElementById('uiSizePanel');
  const fab   = document.getElementById('sizeFab');
  if (panel && fab && !panel.contains(e.target) && !fab.contains(e.target)) {
    panel.classList.remove('open');
  }
});

// 저장된 크기 복원
(function() {
  let saved = 3;
  try { const v = localStorage.getItem('ui_size'); if (v) saved = parseInt(v); } catch(e) {}
  if (isNaN(saved) || saved < 1 || saved > 5) saved = 3;
  applySize(saved);
})();
</script>
