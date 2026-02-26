var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
const form = document.getElementById('paymentForm');
const btnEnviar = document.getElementById('btnEnviar');
const btnText = document.getElementById('btnText');
const spinner = document.getElementById('spinner');
const fileInput = document.getElementById('fileInput');
const previewContainer = document.getElementById('previewContainer');
const imagePreview = document.getElementById('imagePreview');
const removePreviewBtn = document.getElementById('removePreview');
// 2. Lógica de Previsualización y Validación de Archivo
fileInput.addEventListener('change', function () {
    const file = this.files ? this.files[0] : null;
    if (file) {
        // Validación 1: Tipo de archivo
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecciona un archivo de imagen válido (JPG, PNG).');
            resetFile();
            return;
        }
        // Validación 2: Tamaño máximo (2MB)
        const maxSize = 2 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('El archivo es muy pesado. El límite es 2MB.');
            resetFile();
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            var _a;
            if ((_a = e.target) === null || _a === void 0 ? void 0 : _a.result) {
                imagePreview.src = e.target.result;
                previewContainer.classList.remove('hidden');
            }
        };
        reader.readAsDataURL(file);
    }
});
const resetFile = () => {
    fileInput.value = '';
    previewContainer.classList.add('hidden');
    imagePreview.src = '#';
};
removePreviewBtn.addEventListener('click', resetFile);
// 3. Envío del Formulario (FETCH hacia el mismo nivel de carpeta)
form.addEventListener('submit', (e) => __awaiter(this, void 0, void 0, function* () {
    e.preventDefault();
    toggleLoading(true);
    const formData = new FormData(form);
    try {
        /**
         * CAMBIO CLAVE: Ahora el fetch apunta directamente a 'procesar_pago.php'
         * ya que el servidor tiene como raíz la carpeta 'public'.
         */
        const response = yield fetch('procesar_pago.php', {
            method: 'POST',
            body: formData
        });
        // Intentamos obtener el JSON de la respuesta
        const result = yield response.json();
        if (result.status === 'success') {
            alert(`¡Éxito! ${result.message}`);
            form.reset();
            resetFile();
        }
        else {
            // Error controlado desde el backend (ej: referencia duplicada)
            alert(`Atención: ${result.message}`);
        }
    }
    catch (error) {
        /**
         * Este error ocurre si PHP devuelve algo que NO es JSON (como un error 404 o 500)
         * o si no hay conexión con el servidor.
         */
        console.error('Error de parseo o conexión:', error);
        alert('No se pudo procesar la respuesta del servidor. Verifica que los archivos estén en la carpeta pública.');
    }
    finally {
        toggleLoading(false);
    }
}));
/**
 * Control visual del botón
 */
function toggleLoading(isLoading) {
    btnEnviar.disabled = isLoading;
    if (isLoading) {
        btnEnviar.classList.add('opacity-50', 'cursor-not-allowed');
        btnText.innerText = "PROCESANDO...";
        spinner.classList.remove('hidden');
    }
    else {
        btnEnviar.classList.remove('opacity-50', 'cursor-not-allowed');
        btnText.innerText = "ENVIAR DECLARACIÓN";
        spinner.classList.add('hidden');
    }
}
