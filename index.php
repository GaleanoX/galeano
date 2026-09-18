<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Visor PDF</title>

    <!-- PDF.js desde CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { display: flex; flex-direction: column; background: #525659; overflow: hidden; }

        /* Barra superior */
        header {
            background: #1e1e1e;
            color: #fff;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,.35);
            z-index: 10;
            flex-wrap: wrap;
        }
        header .info { font-size: 13px; line-height: 1.3; }
        header .info small { opacity: .7; font-size: 11px; }

        .acciones { display: flex; gap: 8px; }
        .acciones a {
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            background: #0066cc;
            transition: background .2s;
            white-space: nowrap;
            user-select: none;
        }
        .acciones a:active { background: #0052a3; }

        /* Contenedor del PDF */
        #visor {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        /* Cada página del PDF */
        #visor canvas {
            max-width: 100%;
            height: auto;
            background: #fff;
            box-shadow: 0 3px 10px rgba(0,0,0,.4);
            border-radius: 3px;
            display: block;
        }

        /* Mensaje de estado */
        #estado {
            color: #fff;
            text-align: center;
            padding: 40px 20px;
            font-size: 15px;
            opacity: .85;
        }

        /* Barra inferior de paginación */
        #paginador {
            background: #1e1e1e;
            color: #fff;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            font-size: 14px;
            box-shadow: 0 -2px 6px rgba(0,0,0,.3);
            z-index: 10;
        }
        #paginador button {
            background: #0066cc;
            color: #fff;
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }
        #paginador button:active { background: #0052a3; }
        #paginador button:disabled { background: #444; color: #888; cursor: not-allowed; }
        #paginador .num { min-width: 70px; text-align: center; font-variant-numeric: tabular-nums; }

        /* Ocultar paginador si solo hay 1 página */
        #paginador.oculto { display: none; }
    </style>
</head>
<body>

    <header>
        <div class="info">
            📄 <strong>Documento PDF</strong>
        </div>
        <div class="acciones">
            <!-- Único enlace de descarga: solo actúa si el usuario lo pulsa -->
            <a href="NOM-251-SSA1-2009.pdf" download>⬇️ Descargar</a>
        </div>
    </header>

    <div id="visor">
        <div id="estado">⏳ Cargando documento…</div>
    </div>

    <div id="paginador" class="oculto">
        <button id="btnPrev">◀ Anterior</button>
        <span class="num" id="numPag">1 / 1</span>
        <button id="btnNext">Siguiente ▶</button>
    </div>

<script>
// Configurar el worker de PDF.js
pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const URL_PDF   = 'NOM-251-SSA1-2009.pdf';   // <-- ruta del PDF
const visor     = document.getElementById('visor');
const estado    = document.getElementById('estado');
const paginador = document.getElementById('paginador');
const numPag    = document.getElementById('numPag');
const btnPrev   = document.getElementById('btnPrev');
const btnNext   = document.getElementById('btnNext');

let pdfDoc = null;
let paginaActual = 1;
let renderizado = false;

/**
 * Calcula el viewport según el ancho de pantalla y el DPR
 * para obtener buena nitidez en móviles (retina).
 */
function calcularViewport(page) {
    const anchoDisponible = visor.clientWidth - 24; // padding
    const viewportBase    = page.getViewport({ scale: 1 });
    const escala          = anchoDisponible / viewportBase.width;
    const dpr             = window.devicePixelRatio || 1;
    return page.getViewport({ scale: escala * dpr });
}

/**
 * Renderiza una página concreta.
 */
async function renderPagina(num) {
    if (renderizado) return;
    renderizado = true;

    try {
        const page     = await pdfDoc.getPage(num);
        const viewport = calcularViewport(page);

        // Limpiar visor
        visor.innerHTML = '';

        const canvas = document.createElement('canvas');
        const ctx    = canvas.getContext('2d');

        canvas.width  = viewport.width;
        canvas.height = viewport.height;
        // El CSS controla el tamaño visible; el canvas interno va a alta resolución
        canvas.style.width  = (viewport.width  / (window.devicePixelRatio || 1)) + 'px';
        canvas.style.height = (viewport.height / (window.devicePixelRatio || 1)) + 'px';

        visor.appendChild(canvas);

        await page.render({ canvasContext: ctx, viewport }).promise;

        // Actualizar paginador
        numPag.textContent = num + ' / ' + pdfDoc.numPages;
        btnPrev.disabled = (num <= 1);
        btnNext.disabled = (num >= pdfDoc.numPages);

        // Scroll al inicio al cambiar de página
        visor.scrollTop = 0;

    } catch (e) {
        console.error(e);
        visor.innerHTML = '<div id="estado">❌ Error al mostrar la página.</div>';
    } finally {
        renderizado = false;
    }
}

/**
 * Carga inicial del PDF.
 */
(async function init() {
    try {
        pdfDoc = await pdfjsLib.getDocument(URL_PDF).promise;

        // Mostrar paginador solo si hay más de 1 página
        if (pdfDoc.numPages > 1) {
            paginador.classList.remove('oculto');
        }

        await renderPagina(1);

    } catch (e) {
        console.error(e);
        visor.innerHTML = '<div id="estado">❌ No se pudo cargar el PDF.<br>Verifica el archivo.</div>';
    }
})();

// Botones de navegación
btnPrev.addEventListener('click', () => {
    if (paginaActual > 1) {
        paginaActual--;
        renderPagina(paginaActual);
    }
});
btnNext.addEventListener('click', () => {
    if (pdfDoc && paginaActual < pdfDoc.numPages) {
        paginaActual++;
        renderPagina(paginaActual);
    }
});

// Re-renderizar al rotar el dispositivo o cambiar tamaño de ventana
let timerResize;
window.addEventListener('resize', () => {
    clearTimeout(timerResize);
    timerResize = setTimeout(() => {
        if (pdfDoc) renderPagina(paginaActual);
    }, 250);
});
</script>

</body>
</html>
