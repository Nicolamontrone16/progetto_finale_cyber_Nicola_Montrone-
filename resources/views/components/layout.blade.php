<!DOCTYPE html>
<html lang="it" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#07111f">
    <title>{{ isset($title) ? $title . ' · Cyber Blog' : 'Cyber Blog' }}</title>
    <script>
        document.documentElement.dataset.theme = localStorage.getItem('cyber-theme')
            || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">Salta al contenuto</a>
    <x-navbar />

    <div class="toast-zone container" aria-live="polite" aria-atomic="true">
        @if (session('message'))
            <div class="alert alert-success alert-dismissible fade show" role="status">
                <strong>Operazione completata.</strong> {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi messaggio"></button>
            </div>
        @endif
        @if (session('alert'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Attenzione.</strong> {{ session('alert') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi messaggio"></button>
            </div>
        @endif
    </div>

    <main id="main-content" class="site-main" tabindex="-1">
        {{ $slot }}
    </main>

    <x-footer />

    @if (request()->routeIs('articles.create', 'articles.edit'))
        <script src="https://cdn.tiny.cloud/1/6aujx1sqwo157ot7gbesha78g0qbl2ci3hz6lw4ddt7url4e/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
        <script>
            tinymce.init({
                selector: 'textarea#body',
                plugins: 'anchor autolink charmap codesample image link lists table wordcount',
                toolbar: 'undo redo | blocks | bold italic underline | link image | numlist bullist | blockquote code | removeformat',
                valid_elements: 'p,br,strong,b,em,i,u,h1,h2,h3,h4,ul,ol,li,blockquote,a[href|title],img[src|alt|title|width|height],code,pre,hr,table,thead,tbody,tr,th,td',
                invalid_elements: 'script,iframe,object,embed,applet,form,input,button,meta,base,style,svg,math',
                min_height: 460,
                menubar: false,
                branding: false,
                content_style: 'body { font-family: Inter, system-ui, sans-serif; font-size: 16px; line-height: 1.75; }',
            });
        </script>
    @endif
</body>
</html>
