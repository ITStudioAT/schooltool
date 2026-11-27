<div id="app-preloader">
    <div id="homepage-startup-progress" role="progressbar" aria-label="Schooltool wird geladen"></div>
    <p id="homepage-startup-error" role="alert" hidden>Schooltool konnte nicht geladen werden.</p>
    <a id="homepage-startup-retry" href="{{ request()->getRequestUri() }}" hidden>Seite neu laden</a>
    <noscript>
        <style>#homepage-startup-progress { display: none; }</style>
        <p>Bitte aktivieren Sie JavaScript, um Schooltool zu öffnen.</p>
    </noscript>
</div>
<style>
    #app-preloader {
        position: fixed;
        inset: 0;
        padding: 24px;
        background: #fafafa;
        color: #10263a;
        font: 1rem system-ui, sans-serif;
    }
    #homepage-startup-progress {
        position: absolute;
        top: 0;
        left: 0;
        width: min(24vw, 120px);
        height: 3px;
        background: #37474f;
    }
    #homepage-startup-retry {
        color: inherit;
    }
</style>
<script>
    window.setTimeout(() => {
        const progress = document.getElementById('homepage-startup-progress');
        if (!progress) return;

        progress.hidden = true;
        document.getElementById('homepage-startup-error').hidden = false;
        document.getElementById('homepage-startup-retry').hidden = false;
    }, 15000);
</script>
