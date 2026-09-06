(() => {
	const FORM = '.chidemoon-search-form'

	const fieldIsEmpty = (form) => {
		const input = form.querySelector('input[type="search"]')

		return !input || !input.value.trim()
	}

	const sync = (form) => {
		const button = form.querySelector('button[type="submit"]')

		if (button) {
			button.disabled = fieldIsEmpty(form)
		}
	}

	const syncFromEvent = (event) => {
		const form = event.target.closest ? event.target.closest(FORM) : null

		if (form) {
			sync(form)
		}
	}

	document.querySelectorAll(FORM).forEach(sync)

	document.addEventListener('input', syncFromEvent)

	document.addEventListener('keyup', (event) => {
		if (event.key === 'Escape') {
			syncFromEvent(event)
		}
	})

	document.addEventListener('submit', (event) => {
		if (event.target.matches && event.target.matches(FORM) && fieldIsEmpty(event.target)) {
			event.preventDefault()
		}
	})
})()
