export async function request(url, method = "GET", body) {
    const response = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            ...(body ? { "Content-Type": "application/json" } : {}),
            "X-CSRF-TOKEN":
                document.querySelector('meta[name="csrf-token"]')?.content ||
                "",
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const data = response.status === 204 ? {} : await response.json();
    if (!response.ok)
        throw new Error(
            response.status === 429
                ? "Muitas tentativas. Aguarde um minuto."
                : Object.values(data.errors || {}).flat()[0] ||
                      data.message ||
                      "Não foi possível concluir.",
        );
    return data;
}
