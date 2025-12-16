//Crear campo
const createInput = (name, placeholder, additionalAttributes = {}) => {
    return createElement('input', {
        type: 'text',
        name: `${name}[]`,
        placeholder,
        ...additionalAttributes
    });
};

//Crear Lista desplegable
const createSelect = (name, options) => {
    const select = createElement('select', {
        name: `${name}[]`,
        classList: ['border', 'p-2', 'w-60'],
		required: ''
    });
    
    // Agregar opción por defecto
    const defaultOption = createElement('option', { value: '' });
    defaultOption.textContent = 'Seleccione una opción';
    defaultOption.disabled = true;
    defaultOption.selected = true;
    select.appendChild(defaultOption);
    
    options.forEach(option => {
        const optionElement = createElement('option', { value: option.id });
        optionElement.textContent = option.descripcion;
        select.appendChild(optionElement);
    });
    return select;
};