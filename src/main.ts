const form = document.getElementById('paymentForm') as HTMLFormElement;
const btnEnviar = document.getElementById('btnEnviar') as HTMLButtonElement;
const btnText = document.getElementById('btnText') as HTMLElement;
const spinner = document.getElementById('spinner') as HTMLElement;
const fileInput = document.getElementById('fileInput') as HTMLInputElement;
const previewContainer = document.getElementById('previewContainer') as HTMLDivElement;
const imagePreview = document.getElementById('imagePreview') as HTMLImageElement;
const removePreviewBtn = document.getElementById('removePreview') as HTMLButtonElement;

// 2. Lógica de Previsualización y Validación de Archivo
fileInput.addEventListener('change', function(this: HTMLInputElement) {
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
        reader.onload = (e: ProgressEvent<FileReader>) => {
            if (e.target?.result) {
                imagePreview.src = e.target.result as string;
                previewContainer.classList.remove('hidden');
            }
        };
        reader.readAsDataURL(file);
    }
});

const resetFile = (): void => {
    fileInput.value = '';
    previewContainer.classList.add('hidden');
    imagePreview.src = '#';
};

removePreviewBtn.addEventListener('click', resetFile);

// 3. Envío del Formulario (FETCH hacia el mismo nivel de carpeta)
form.addEventListener('submit', async (e: Event) => {
    e.preventDefault();

    toggleLoading(true);

    const formData = new FormData(form);

    try {
        /**
         * CAMBIO CLAVE: Ahora el fetch apunta directamente a 'procesar_pago.php'
         * ya que el servidor tiene como raíz la carpeta 'public'.
         */
        const response = await fetch('procesar_pago.php', {
            method: 'POST',
            body: formData
        });

        // Intentamos obtener el JSON de la respuesta
        const result = await response.json();

        if (result.status === 'success') {
            alert(`¡Éxito! ${result.message}`);
            form.reset();
            resetFile();
        } else {
            // Error controlado desde el backend (ej: referencia duplicada)
            alert(`Atención: ${result.message}`);
        }

    } catch (error) {
        /**
         * Este error ocurre si PHP devuelve algo que NO es JSON (como un error 404 o 500)
         * o si no hay conexión con el servidor.
         */
        console.error('Error de parseo o conexión:', error);
        alert('No se pudo procesar la respuesta del servidor. Verifica que los archivos estén en la carpeta pública.');
    } finally {
        toggleLoading(false);
    }
});

/**
 * Control visual del botón
 */
function toggleLoading(isLoading: boolean): void {
    btnEnviar.disabled = isLoading;
    if (isLoading) {
        btnEnviar.classList.add('opacity-50', 'cursor-not-allowed');
        btnText.innerText = "PROCESANDO...";
        spinner.classList.remove('hidden');
    } else {
        btnEnviar.classList.remove('opacity-50', 'cursor-not-allowed');
        btnText.innerText = "ENVIAR DECLARACIÓN";
        spinner.classList.add('hidden');
    }
}