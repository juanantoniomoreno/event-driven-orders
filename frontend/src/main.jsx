import React, { useState, useEffect } from "react";
import ReactDOM from "react-dom/client";

const API = "/api";

function App() {
	const [orders, setOrders] = useState([]);
	const [email, setEmail] = useState("");
	const [items, setItems] = useState("");
	const [total, setTotal] = useState("");

	const fetchOrders = async () => {
		const res = await fetch(`${API}/orders`);
		const data = await res.json();
		setOrders(data);
	};

	useEffect(() => {
		fetchOrders();

		// Subscribe to real-time order status updates via Mercure
		const mercureUrl = "http://localhost:3001/.well-known/mercure";
		const topic = "/orders/*/status";
		const eventSource = new EventSource(
			`${mercureUrl}?topic=${encodeURIComponent(topic)}`,
		);

		eventSource.onmessage = (event) => {
			const data = JSON.parse(event.data);
			// Update only the order that changed
			setOrders((prev) =>
				prev.map((order) =>
					order.id === data.orderId ? { ...order, status: data.status } : order,
				),
			);
		};

		return () => eventSource.close();
	}, []);

	const createOrder = async (e) => {
		e.preventDefault();
		await fetch(`${API}/orders`, {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({
				customerEmail: email,
				items: items.split(",").map((s) => s.trim()),
				total: parseFloat(total),
			}),
		});
		setEmail("");
		setItems("");
		setTotal("");
		fetchOrders();
	};

	return (
		<div style={{ maxWidth: 600, margin: "0 auto", padding: 20 }}>
			<h1>Event-Driven Orders</h1>
			<form onSubmit={createOrder}>
				<input
					placeholder="Email"
					value={email}
					onChange={(e) => setEmail(e.target.value)}
					required
				/>
				<br />
				<input
					placeholder="Items (comma separated)"
					value={items}
					onChange={(e) => setItems(e.target.value)}
					required
				/>
				<br />
				<input
					placeholder="Total"
					value={total}
					onChange={(e) => setTotal(e.target.value)}
					required
					type="number"
					step="0.01"
				/>
				<br />
				<button type="submit">Create Order</button>
			</form>
			<h2>Orders</h2>
			<ul>
				{orders.map((o) => (
					<li key={o.id}>
						<strong>{o.id}</strong> — {o.customerEmail} — ${o.total} —{" "}
						<em>{o.status}</em>
					</li>
				))}
			</ul>
		</div>
	);
}

ReactDOM.createRoot(document.getElementById("root")).render(
	<React.StrictMode>
		<App />
	</React.StrictMode>,
);
