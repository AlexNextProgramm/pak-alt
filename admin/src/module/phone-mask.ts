import IMask from 'imask';

export function applyPhoneMask(input: HTMLInputElement) {
    const digits = input.value.replace(/\D/g, '');
    if (digits.length === 11 && (digits[0] === '7' || digits[0] === '8')) {
        const normalized = digits[0] === '8' ? `7${digits.slice(1)}` : digits;
        input.value = `+7 (${normalized.slice(1, 4)}) ${normalized.slice(4, 7)}-${normalized.slice(7, 9)}-${normalized.slice(9, 11)}`;
    }

    return IMask(input, {
        mask: '+{7} (000) 000-00-00',
    });
}