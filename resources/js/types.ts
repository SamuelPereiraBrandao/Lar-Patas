export interface Pet {
    id: number;
    name: string;
    species: "dog" | "cat";
    size: "small" | "medium" | "large";
    status: "available" | "in_process" | "adopted";
    city: string;
    temperament: string;
    description: string;
    image_url: string | null;
    age_label: string;
}
export interface Adoption {
    id: number;
    applicant_name: string;
    email: string;
    phone: string;
    housing_type: string;
    status: "pending" | "approved" | "rejected";
    pet?: Pick<Pet, "id" | "name">;
}
