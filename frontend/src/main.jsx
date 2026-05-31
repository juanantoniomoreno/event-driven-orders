import React, { useState, useEffect, useRef } from "react";
import ReactDOM from "react-dom/client";

const API = "/api";
const MERCURE_URL = "http://localhost:3001/.well-known/mercure";

function App() {
	const [orders, setOrders] = useState([]);
	const [email, setEmail] = useState("");
	const [items, setItems] = useState("");
	const [total, setTotal] = useState("");
	const eventSourcesRef = useRef({});

	const fetchOrders = async () => {
		const res = await fetch(`${API}/orders`);
		const data = await res.json();
		setOrders(data);
	};

	// Subscribe to Mercure SSE for a specific order
	const subscribeToOrder = (orderId) => {
		// Avoid duplicate subscriptions
		if (eventSourcesRef.current[orderId]) return;

		const topic = `/orders/${orderId}/status`;
		const es = new EventSource(
			`${MERCURE_URL}?topic=${encodeURIComponent(topic)}`,
		);

		es.onmessage = (event) => {
			const data = JSON.parse(event.data);
			setOrders((prev) =>
				prev.map((order) =>
					order.id === data.orderId
						? { ...order, status: data.status }
						: order,
				),
			);
			// Clean up subscription once the order is updated
			es.close();
			delete eventSourcesRef.current[orderId];
		};

		es.onerror = () => {
			es.close();
			delete eventSourcesRef.current[orderId];
		};

		eventSourcesRef.current[orderId] = es;
	};

	// Subscribe to existing orders on mount
	useEffect(() => {
		fetchOrders();
		return () => {
			// Cleanup all event sources
			Object.values(eventSourcesRef.current).forEach((es) => es.close());
		};
	}, []);

	// Subscribe to new orders when they're added to the list
	useEffect(() => {
		orders.forEach((order) => {
			if (order.status === "pending") {
				subscribeToOrder(order.id);
			}
		});
	}, [orders]);

	const createOrder = async (e) => {
		e.preventDefault();
		const res = await fetch(`${API}/orders`, {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({
				customerEmail: email,
				items: items.split(",").map((s) => s.trim()),
				total: parseFloat(total),
			}),
		});
		const newOrder = await res.json();
		setEmail("");
		setItems("");
		setTotal("");
		// Add the new order and subscribe to its SSE updates
		setOrders((prev) => [...prev, newOrder]);
		subscribeToOrder(newOrder.id);
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
