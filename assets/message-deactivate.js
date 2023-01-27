        window.onload = function(){
            document.querySelector('[data-slug="fast-index"] a').addEventListener('click', function(event){
                event.preventDefault()
                var urlRedirect = document.querySelector('[data-slug="fast-index"] a').getAttribute('href');
                if (confirm('Do you want to delete data too?')) {
                    window.location.href = urlRedirect+"&fi_delete=true";
                } else {
                    window.location.href = urlRedirect+"&fi_delete=false";
                }
            })
        }