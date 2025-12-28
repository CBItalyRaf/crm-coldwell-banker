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

// Autocomplete search
const searchInput=document.getElementById('searchInput');
const searchResults=document.getElementById('searchResults');
let searchTimeout;

if(searchInput && searchResults){
searchInput.addEventListener('input',function(){
clearTimeout(searchTimeout);
const query=this.value.trim();
if(query.length<2){
searchResults.classList.remove('active');
return;
}
searchTimeout=setTimeout(()=>{
fetch('https://admin.mycb.it/search_api.php?q='+encodeURIComponent(query))
.then(r=>r.json())
.then(data=>{
if(data.length===0){
searchResults.innerHTML='<div style="padding:1rem;text-align:center;color:#6D7180">Nessun risultato</div>';
}else{
searchResults.innerHTML=data.map(item=>`
<div class="search-item" onclick="window.location.href='${item.url}'">
<div class="search-item-title">${item.title}</div>
<div class="search-item-meta">${item.meta}</div>
</div>
`).join('');
}
searchResults.classList.add('active');
});
},300);
});

document.addEventListener('click',function(e){
if(!e.target.closest('.search-container')){
searchResults.classList.remove('active');
}
});
}
</script>
</body>
</html>
