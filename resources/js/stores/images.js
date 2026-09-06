export async function preparePhoto(file, maxSide = 1600) {
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type))
        throw new Error("Escolha uma foto JPG, PNG ou WebP.");
    const bitmap = await createImageBitmap(file);
    try {
        const scale = Math.min(
            1,
            maxSide / Math.max(bitmap.width, bitmap.height),
        );
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const context = canvas.getContext("2d");
        context.fillStyle = "#ffffff";
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) =>
            canvas.toBlob(resolve, "image/jpeg", 0.85),
        );
        if (!blob) throw new Error("Não foi possível preparar a foto.");
        return new File([blob], file.name.replace(/\.[^.]+$/, "") + ".jpg", {
            type: "image/jpeg",
        });
    } finally {
        bitmap.close();
    }
}
export function uploadPhotos(url, form, onProgress = () => {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", url);
        xhr.setRequestHeader("Accept", "application/json");
        xhr.setRequestHeader(
            "X-CSRF-TOKEN",
            document.querySelector('meta[name="csrf-token"]')?.content || "",
        );
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable)
                onProgress(Math.round((event.loaded / event.total) * 100));
        };
        xhr.onerror = () =>
            reject(new Error("Falha de conexão ao enviar fotos."));
        xhr.onload = () => {
            try {
                const data = JSON.parse(xhr.responseText);
                if (xhr.status >= 200 && xhr.status < 300) resolve(data);
                else
                    reject(
                        new Error(
                            Object.values(data.errors || {}).flat()[0] ||
                                data.message ||
                                "Não foi possível enviar as fotos.",
                        ),
                    );
            } catch (error) {
                reject(error);
            }
        };
        xhr.send(form);
    });
}
