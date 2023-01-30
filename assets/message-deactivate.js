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


        window.onload = function(){
            document.querySelector('input[readonly="true"]').addEventListener('click', function(event){
                event.preventDefault();
                this.checked = false;
                if (confirm('Please upgrade to premium')) {
                    window.location.href = "admin.php?page=fast-index-pricing";
                }

            })
        }


