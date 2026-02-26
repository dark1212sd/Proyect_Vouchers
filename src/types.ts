// Estructura del Voucher según nuestra colección en MongoDB
export interface IVoucher {
    cedula: string;
    referencia: string;
    monto: number;
    comprobante: File; // Para el manejo del archivo en el frontend
}

// Respuesta que esperamos del backend PHP
export interface IApiResponse {
    status: 'success' | 'error';
    message: string;
    id?: string; // ID generado por MongoDB
}

// Roles de usuario para el sistema
export type UserRole = 'administrador' | 'vecino';