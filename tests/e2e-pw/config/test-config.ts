export const testConfig = {
	url: 'http://localhost:8885/',
	users: {
		admin: {
			username: 'admin',
			password: 'password',
		},
		customer: {
			username: 'customer',
			password: 'password',
		},
	},
	products: {
		simple: {
			name: 'Beanie',
			price: '20.00',
		},
		// Cheap enough that the payment already sitting at the test wallet's first address covers it.
		cheap: {
			name: 'Bitcoin Sticker',
			price: '3.00',
		},
	},
	addresses: {
		customer: {
			billing: {
				firstname: 'John',
				lastname: 'Doe',
				company: 'Automattic',
				country: 'US',
				addressfirstline: 'addr 1',
				addresssecondline: 'addr 2',
				city: 'San Francisco',
				state: 'CA',
				postcode: '94107',
				phone: '123456789',
				email: 'john.doe@example.com',
			},
		},
	},
};
