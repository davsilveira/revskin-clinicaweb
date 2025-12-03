/**
 * Utility functions for form validations
 */

/**
 * Validates a CPF (Brazilian individual taxpayer registration)
 * @param {string} cpf - CPF with or without formatting
 * @returns {boolean} - True if CPF is valid
 */
export function validateCPF(cpf) {
    if (!cpf) return false;
    
    // Remove non-numeric characters
    const cleanCPF = cpf.replace(/\D/g, '');
    
    // Check if has 11 digits
    if (cleanCPF.length !== 11) return false;
    
    // Check if all digits are the same (invalid CPF)
    if (/^(\d)\1+$/.test(cleanCPF)) return false;
    
    // Validate first digit
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(cleanCPF.charAt(i)) * (10 - i);
    }
    let digit = 11 - (sum % 11);
    if (digit >= 10) digit = 0;
    if (digit !== parseInt(cleanCPF.charAt(9))) return false;
    
    // Validate second digit
    sum = 0;
    for (let i = 0; i < 10; i++) {
        sum += parseInt(cleanCPF.charAt(i)) * (11 - i);
    }
    digit = 11 - (sum % 11);
    if (digit >= 10) digit = 0;
    if (digit !== parseInt(cleanCPF.charAt(10))) return false;
    
    return true;
}

/**
 * Validates a CNPJ (Brazilian company taxpayer registration)
 * @param {string} cnpj - CNPJ with or without formatting
 * @returns {boolean} - True if CNPJ is valid
 */
export function validateCNPJ(cnpj) {
    if (!cnpj) return false;
    
    // Remove non-numeric characters
    const cleanCNPJ = cnpj.replace(/\D/g, '');
    
    // Check if has 14 digits
    if (cleanCNPJ.length !== 14) return false;
    
    // Check if all digits are the same (invalid CNPJ)
    if (/^(\d)\1+$/.test(cleanCNPJ)) return false;
    
    // Validate first digit
    let length = cleanCNPJ.length - 2;
    let numbers = cleanCNPJ.substring(0, length);
    const digits = cleanCNPJ.substring(length);
    let sum = 0;
    let pos = length - 7;
    
    for (let i = length; i >= 1; i--) {
        sum += numbers.charAt(length - i) * pos--;
        if (pos < 2) pos = 9;
    }
    
    let result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
    if (result !== parseInt(digits.charAt(0))) return false;
    
    // Validate second digit
    length = length + 1;
    numbers = cleanCNPJ.substring(0, length);
    sum = 0;
    pos = length - 7;
    
    for (let i = length; i >= 1; i--) {
        sum += numbers.charAt(length - i) * pos--;
        if (pos < 2) pos = 9;
    }
    
    result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
    if (result !== parseInt(digits.charAt(1))) return false;
    
    return true;
}

/**
 * Validates an email address
 * @param {string} email - Email address to validate
 * @returns {boolean} - True if email is valid
 */
export function validateEmail(email) {
    if (!email) return false;
    
    // Basic email regex pattern
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    return emailRegex.test(email);
}

