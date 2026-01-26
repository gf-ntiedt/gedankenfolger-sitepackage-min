/* Created by Content Blocks */
console.log('Address');
const searchInput = document.getElementById('gedankenfolger_addresslist-search-input');
const addresses = document.querySelectorAll('.gedankenfolger_addresslist address');

searchInput.addEventListener('input', function () {
    const query = this.value.trim().toLowerCase();

    addresses.forEach(address => {
        console.log(address);
        // Hole alle Attribute des aktuellen <address>-Elements
        const attrs = address.attributes;
        let match = false;

        // Iteriere durch alle Attribute und prüfe auf den Suchbegriff 
        for (let i = 0; i < attrs.length; i++) {
            const attrValue = attrs[i].value.toLowerCase();
            if (attrValue.includes(query)) {
                match = true;
                break;
            }
        }

        // Zeige oder verstecke das <address>-Element basierend auf dem Suchergebnis
        if (match || query === '') {
            address.classList.remove('d-none');
        } else {
            address.classList.add('d-none');
        }
    });
});