function updateBottle(pressed, direction) {
	if (pressed == 0 && direction == 0) {
		document.getElementById("upBottle").src = "upBottleDark.gif";
		updateAmount(2);
		updateGlass();
	} else if (pressed == 1 && direction == 0) {
		document.getElementById("upBottle").src = "upBottle.gif";
	} else if (pressed == 0 && direction == 1) {
		document.getElementById("downBottle").src = "downBottleDark.gif";
		updateAmount(-2);
		updateGlass();
	} else {
		document.getElementById("downBottle").src = "downBottle.gif";
	}
}

function updateAmount(change) {
	text = document.getElementById("amt").value;
	amount = parseInt(text);
	amount += change;
	if(amount < 2) {
		amount = 2;
	} else if (amount > 16) {
		amount = 16;
	}
	document.getElementById('amt').value = amount;
	document.getElementById('sub').value = "Pour\n" + amount + " oz!";
	enableButton(amount);
}

function updateGlass() {
	amount = document.getElementById('amt').value;
	color = document.getElementById('color').value;
	if(color == "Light") {
		filename = "glass/light/" + amount + ".gif";
		document.getElementById('light_button').style.textDecoration = "underline";
		document.getElementById('dark_button').style.textDecoration = "none";
	} else {
		filename = "glass/dark/" + amount + ".gif";
		document.getElementById('light_button').style.textDecoration = "none";
		document.getElementById('dark_button').style.textDecoration = "underline";
	}
	document.getElementById("glass").src = filename;
}

function updateColor(color) {
	document.getElementById("color").value = color;
	updateGlass();
}

function reloadGlass(amount, color) {
	document.getElementById('amt').value = amount;
	document.getElementById("color").value = color;
	updateGlass();
}

function enableButton(amt) {
	balanceText = document.getElementById("bal").innerHTML;
	balance = parseInt(balanceText);
	if(balance >= amt) {
		document.getElementById("sub").disabled = false;
	} else {
		document.getElementById("sub").disabled = true;
	}
}