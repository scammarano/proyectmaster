</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.setCtx = function(title, actionsHtml){
  const t=document.getElementById('ctxTitle');
  const a=document.getElementById('ctxActions');
  if(t) t.innerHTML = title || '';
  if(a) a.innerHTML = actionsHtml || '';
};
</script>
</body></html>
