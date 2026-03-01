// Selects the header element in the document
let header = document.querySelector('.header');

// When the menu button is clicked, it adds the 'active' class to the header (usually to display the menu)
document.querySelector('#menu-btn').onclick = () => {
   header.classList.add('active');
}

// When the close button is clicked, it removes the 'active' class from the header (hides the menu)
document.querySelector('#close-btn').onclick = () => {
   header.classList.remove('active');
}

// When the page is scrolled, it removes the 'active' class from the header (hides the menu)
window.onscroll = () => {
   header.classList.remove('active');
}

// Loops through all number input fields
document.querySelectorAll('input[type="number"]').forEach(inputNumbmer => {
   // When input changes, it checks if the value length exceeds the maximum length
   inputNumbmer.oninput = () => {
      // If the input length exceeds the max length, it trims the input value to the max length
      if(inputNumbmer.value.length > inputNumbmer.maxLength) inputNumbmer.value = inputNumbmer.value.slice(0, inputNumbmer.maxLength);
   }
});
