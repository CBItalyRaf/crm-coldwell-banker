</div><!-- Fine container -->

<div class="footer">
<div class="footer-content">
Powered by Coldwell Banker Italy
</div>
</div>

<script>
// Menu dropdown
document.querySelectorAll('.nav-button').forEach(btn=>{
btn.addEventListener('click',function(e){
e.stopPropagation();
const parent=this.closest('.nav-item');
document.querySelectorAll('.nav-item').forEach(item=>{
if(item!==parent)item.classList.remove('open');
});
parent.classList.toggle('open');
});
});

document.addEventListener('click',()=>{
document.querySelectorAll('.nav-item').forEach(item=>item.classList.remove('open'));
});
</script>
</body>
</html>
