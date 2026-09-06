<?php

namespace Database\Seeders;

use App\Models\Adoption;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\DirectMessageLike;
use App\Models\FriendRequest;
use App\Models\Message;
use App\Models\Pet;
use App\Models\PetLike;
use App\Models\ProfilePost;
use App\Models\ProfilePostLike;
use App\Models\Role;
use App\Models\Shelter;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Visit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoCommunitySeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var list<User> */
    private array $users = [];

    /** @var list<Pet> */
    private array $pets = [];

    /** @var list<Shelter> */
    private array $shelters = [];

    /** @var array<string, list<string>> */
    private array $images = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os dados de demonstração só podem ser criados em ambiente local ou de testes.');
        }
        if (User::exists() || Pet::exists()) {
            throw new RuntimeException('Use migrate:fresh --seed para criar a demonstração em um banco vazio.');
        }
        $this->prepareImages();
        DB::transaction(function (): void {
            $this->createUsers();
            $this->createShelters();
            $this->createPets();
            $this->createAdoptions();
            $this->createBelinhaInterests();
            $this->createPosts();
            $this->createFriendships();
        });
        $this->command?->info('Demonstração criada: 30 usuários, 48 pets, 6 novas sedes, adoções, visitas e comunidade. Senha das contas: password.');
    }

    private function prepareImages(): void
    {
        $sources = [
            'dog' => ['photo-1552053831-71594a27632d', 'photo-1587300003388-59208cc962cb', 'photo-1543466835-00a7907e9de1', 'photo-1517849845537-4d257902454a'],
            'cat' => ['photo-1514888286974-6c03e2ca1dba', 'photo-1495360010541-f48722b34f7d', 'photo-1573865526739-10659fec78a5', 'photo-1533738363-b7f9aef128ce'],
        ];
        $disk = Storage::disk('public');
        foreach ($sources as $species => $photos) {
            foreach ($photos as $index => $photo) {
                $path = "demo/sources/{$species}-{$index}.jpg";
                if (! $disk->exists($path)) {
                    try {
                        $response = Http::connectTimeout(3)->timeout(8)->get("https://images.unsplash.com/{$photo}?auto=format&fit=crop&w=900&q=80");
                        if ($response->successful() && str_starts_with($response->header('Content-Type') ?? '', 'image/') && @getimagesizefromstring($response->body()) !== false) {
                            $disk->put($path, $response->body());
                        }
                    } catch (ConnectionException) {
                        $this->command?->warn("Foto {$species}-{$index} indisponível; usando imagem local.");
                    }
                }
                if (! $disk->exists($path)) {
                    $cached = $species === 'dog' ? 'pets/seed/1-1.jpg' : 'pets/seed/2-3.jpg';
                    if ($disk->exists($cached)) {
                        $disk->copy($cached, $path);
                    } else {
                        $path = $this->illustration("demo/sources/{$species}-{$index}.svg", $species === 'dog' ? 'Au!' : 'Miau', $index);
                    }
                }
                $this->images[$species][] = $path;
            }
        }
    }

    private function illustration(string $path, string $label, int $index): string
    {
        $colors = ['#0f766e', '#b45309', '#4f46e5', '#be185d', '#0369a1', '#4d7c0f'];
        $color = $colors[$index % count($colors)];
        $label = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');
        Storage::disk('public')->put($path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400"><rect width="600" height="400" fill="'.$color.'"/><circle cx="490" cy="50" r="190" fill="white" opacity=".10"/><circle cx="80" cy="380" r="180" fill="white" opacity=".08"/><text x="300" y="225" text-anchor="middle" fill="white" font-size="100" font-family="Arial,sans-serif" font-weight="bold">'.$label.'</text></svg>');

        return $path;
    }

    private function copyImage(string $source, string $destination): string
    {
        $path = $destination.'.'.pathinfo($source, PATHINFO_EXTENSION);
        Storage::disk('public')->copy($source, $path);

        return $path;
    }

    private function createUsers(): void
    {
        $roles = collect(['admin' => 'Administrador', 'adopter' => 'Adotante', 'donor' => 'Doador'])->map(fn (string $label, string $name) => Role::firstOrCreate(['name' => $name], ['label' => $label]));
        $names = ['Admin Lar & Patas', 'Ana Silva', 'Bruno Costa', 'Carla Souza', 'Diego Almeida', 'Elisa Santos', 'Felipe Rocha', 'Gabriela Lima', 'Henrique Alves', 'Isabela Martins', 'João Pereira', 'Juliana Ribeiro', 'Lucas Oliveira', 'Mariana Gomes', 'Nicolas Ferreira', 'Olívia Barros', 'Pedro Mendes', 'Rafaela Dias', 'Samuel Brandão', 'Tatiana Castro', 'Vinícius Melo', 'Yasmin Nunes', 'André Cardoso', 'Beatriz Freitas', 'Caio Teixeira', 'Daniela Lopes', 'Eduardo Ramos', 'Fernanda Vieira', 'Gustavo Azevedo', 'Helena Batista'];
        $places = [['Blumenau', 'SC'], ['São Paulo', 'SP'], ['Curitiba', 'PR'], ['Campinas', 'SP'], ['Joinville', 'SC'], ['Rio de Janeiro', 'RJ']];
        $password = Hash::make('password');
        foreach ($names as $index => $name) {
            [$city, $state] = $places[$index % count($places)];
            $firstName = Str::lower(Str::ascii(explode(' ', $name)[0]));
            $avatar = $this->illustration("demo/users/{$index}/avatar.svg", Str::upper(Str::substr($name, 0, 1)), $index);
            $banner = $this->copyImage($this->images[$index % 2 ? 'cat' : 'dog'][$index % 4], "demo/users/{$index}/banner");
            $user = User::factory()->create([
                'name' => $name, 'email' => $index === 0 ? 'admin@larepatas.test' : "{$firstName}@larepatas.test", 'password' => $password,
                'city' => $city, 'state' => $state, 'phone' => '119'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                'birth_date' => now()->subYears(25 + $index % 20)->toDateString(), 'housing_type' => $index % 2 ? 'Apartamento' : 'Casa com quintal',
                'has_other_pets' => $index % 3 !== 0, 'household_description' => 'Gosto de passeios ao ar livre e de uma casa cheia de companhia. Tenho tempo e espaço para receber um novo amigo com responsabilidade.',
                'avatar_path' => $avatar, 'banner_path' => $banner, 'is_active' => $index !== 29, 'created_at' => now()->subDays(100 - $index),
            ]);
            $user->roles()->sync($index === 0 ? $roles->pluck('id')->all() : [$roles[$index < 7 ? 'donor' : 'adopter']->id]);
            $this->users[] = $user;
        }
    }

    private function createShelters(): void
    {
        foreach ([['Vale das Patas', 'Centro', 'Blumenau', 'SC'], ['Casa Lapa', 'Lapa', 'São Paulo', 'SP'], ['Amigos do Bosque', 'Centro', 'Curitiba', 'PR'], ['Recanto Animal', 'Cambuí', 'Campinas', 'SP'], ['Patas do Norte', 'América', 'Joinville', 'SC'], ['Lar da Tijuca', 'Tijuca', 'Rio de Janeiro', 'RJ']] as $index => [$name, $district, $city, $state]) {
            $this->shelters[] = Shelter::create(['name' => $name, 'district' => $district, 'city' => $city, 'state' => $state, 'address' => 'Rua dos Ipês, '.(100 + $index * 25), 'active' => $index !== 5]);
        }
    }

    private function createPets(): void
    {
        $names = ['Luna', 'Mingau', 'Thor', 'Amora', 'Bento', 'Mel', 'Simba', 'Nina', 'Paçoca', 'Chico', 'Frida', 'Theo', 'Pipoca', 'Jujuba', 'Tobias', 'Cacau', 'Belinha', 'Tom', 'Kiara', 'Pingo', 'Aurora', 'Oliver', 'Lola', 'Zeca', 'Maya', 'Tito', 'Flor', 'Romeu', 'Jade', 'Bob', 'Nala', 'Fred', 'Lili', 'Max', 'Sushi', 'Biscoito', 'Mari', 'Augusto', 'Dora', 'Apolo', 'Café', 'Lua', 'Bidu', 'Fubá', 'Milo', 'Sol', 'Dengoso', 'Pituca'];
        foreach ($names as $index => $name) {
            $species = $index % 3 === 1 ? 'cat' : 'dog';
            $family = $index >= 36;
            $completed = $index >= 26 && $index < 36;
            $waiting = $index >= 20 && $index < 26;
            $shelter = $this->shelters[$index % 5];
            $owner = $this->users[$family ? $index - 36 : ($completed ? $index - 25 : $index % 7)];
            $cover = $this->copyImage($this->images[$species][$index % 4], "demo/pets/{$index}/cover");
            $gallery = [$this->copyImage($this->images[$species][($index + 1) % 4], "demo/pets/{$index}/gallery")];
            $pet = Pet::factory()->create([
                'name' => $name, 'species' => $species, 'breed' => $species === 'cat' ? 'SRD' : ['SRD', 'Labrador', 'Vira-lata'][$index % 3],
                'sex' => $index % 2 ? 'male' : 'female', 'size' => $species === 'cat' ? 'small' : ['small', 'medium', 'large'][$index % 3],
                'birth_date' => now()->subMonths(7 + $index * 2)->toDateString(),
                'owner_id' => $owner->id, 'ownership_kind' => $family ? 'guardian' : 'adoption',
                'status' => $family || $completed ? 'adopted' : ($waiting ? 'in_process' : 'available'),
                'shelter_id' => $family || $completed ? null : ($index % 9 === 0 && ! $waiting ? null : $shelter->id),
                'city' => $family || $completed ? $owner->city : $shelter->city, 'state' => $family || $completed ? $owner->state : $shelter->state,
                'lives_with_owner' => $family || $completed, 'queue_position' => $index < 20 ? $index + 1 : null,
                'temperament' => $index % 2 ? 'Carinhoso, Calmo, Sociável' : 'Brincalhão, Curioso, Energético',
                'description' => "{$name} adora companhia e transforma os pequenos momentos em alegria. ".($species === 'cat' ? 'Gosta de observar a janela e brincar com bolinhas. Precisa de um lar com telas e cantinhos aconchegantes.' : 'Adora passeios, brinquedos e uma boa soneca depois de brincar. Combina com uma família que queira compartilhar a rotina.'),
                'triage_notes' => $family || $completed ? null : 'Avaliação inicial realizada. A equipe acompanha a adaptação e está disponível para conversar com os interessados.',
                'image_path' => $cover, 'gallery_paths' => $gallery, 'created_at' => now()->subDays(65 - $index),
            ]);
            $this->pets[] = $pet;
            if ($family) {
                $confirmed = $this->users[($index - 35) % 30];
                $pending = $this->users[($index - 22) % 30];
                $pet->caretakers()->attach($confirmed->id, ['status' => 'accepted']);
                $pet->caretakers()->attach($pending->id, ['status' => 'pending']);
                $this->notice($pending, 'pet_caretaker_request', 'Convite para cuidar de '.$name, $owner->name.' convidou você para ser dono de '.$name.'.', ['pet_id' => $pet->id, 'sender_id' => $owner->id]);
            }
            for ($like = 1; $like <= 5 + $index % 8; $like++) {
                PetLike::create(['pet_id' => $pet->id, 'user_id' => $this->users[($index + $like) % 29]->id]);
            }
            foreach (['Que carinha mais querida! Como é a rotina dele?', 'A equipe pode orientar sobre adaptação e cuidados.', 'Adorei conhecer essa história. Torcendo por um lar cheio de carinho!'] as $offset => $body) {
                Message::create(['pet_id' => $pet->id, 'user_id' => $this->users[($index + $offset) % 29]->id, 'body' => $body, 'created_at' => now()->subDays(3)->addHours($offset)]);
            }
        }
    }

    private function createBelinhaInterests(): void
    {
        $pet = Pet::where('name', 'Belinha')->sole();
        $existingApplicants = $pet->adoptions()->pluck('user_id')->all();
        $applicants = collect($this->users)
            ->reject(fn (User $user): bool => in_array($user->id, $existingApplicants, true) || Pet::ownedBy($user->id)->whereKey($pet->id)->exists())
            ->take(10);

        foreach ($applicants as $user) {
            Adoption::create([
                'pet_id' => $pet->id,
                'user_id' => $user->id,
                'applicant_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'housing_type' => $user->housing_type,
                'has_other_pets' => $user->has_other_pets,
                'message' => 'Gostaria de conhecer a Belinha e conversar sobre os cuidados para recebê-la em casa.',
                'status' => 'pending',
            ]);
        }
    }

    private function createAdoptions(): void
    {
        foreach (array_slice($this->pets, 0, 36) as $index => $pet) {
            for ($candidate = 0; $candidate < 3; $candidate++) {
                $selected = $candidate === 0 && $index >= 20;
                $completed = $index >= 26;
                $user = $selected ? $this->users[$completed ? $index - 25 : $index - 19] : $this->users[12 + ($index + $candidate) % 17];
                $date = $completed ? now()->subDays(12 - ($index - 26))->setTime(15, 0) : now()->addDays(2 + $index % 5)->setTime(17, 0);
                $shelter = $this->shelters[$index % 5];
                $place = $shelter->name.', '.$shelter->address.', '.$shelter->city.' - '.$shelter->state;
                $code = (string) (420000 + $index);
                $adoption = Adoption::create([
                    'user_id' => $user->id, 'pet_id' => $pet->id, 'applicant_name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                    'housing_type' => $user->housing_type, 'has_other_pets' => $user->has_other_pets, 'message' => 'Gostaria de conhecer '.$pet->name.'. Já preparei um cantinho e quero conversar sobre a adaptação.',
                    'status' => $selected ? 'approved' : ($completed || $candidate === 2 && $index % 4 === 0 ? 'rejected' : 'pending'),
                    'pickup_at' => $selected ? $date : null, 'pickup_timezone' => $selected ? 'America/Sao_Paulo' : null,
                    'pickup_location' => $selected ? $place : null, 'pickup_message' => $selected ? 'Traga um documento e uma caixa de transporte ou guia. Nossa equipe estará esperando você!' : null,
                    'pickup_code' => $selected && ! $completed ? $code : null, 'released_at' => $selected && $completed ? $date->copy()->addMinutes(15) : null,
                    'scheduled_by' => $selected ? $this->users[0]->id : null, 'released_by' => $selected && $completed ? $this->users[0]->id : null,
                    'created_at' => now()->subDays(18),
                ]);
                if ($candidate === 0 || $candidate === 1 && $index % 3 === 0 || $candidate === 2 && $index % 4 === 0) {
                    Visit::create(['pet_id' => $pet->id, 'adoption_id' => $adoption->id, 'scheduled_at' => $date, 'status' => $candidate === 2 ? 'cancelled' : ($completed ? 'completed' : ($candidate === 0 ? 'confirmed' : 'requested')), 'notes' => 'Encontro com a equipe para conhecer o pet e conversar sobre a adaptação.']);
                }
                if ($selected && ! $completed) {
                    $this->notice($user, 'adoption_pickup', 'Venha buscar '.$pet->name.'!', 'Retirada em '.$date->copy()->timezone('America/Sao_Paulo')->format('d/m/Y H:i').'. Local: '.$place.'. Código: '.$code.'. '.$adoption->pickup_message, ['pet_id' => $pet->id, 'adoption_id' => $adoption->id]);
                } elseif ($selected) {
                    $post = $this->post($user, $pet, 'Uma nova história começa: '.$pet->name.' foi adotado e agora faz parte da minha família! 🐾', $index, 0);
                    $post->forceFill(['created_at' => $date->copy()->addMinutes(15)])->save();
                    $this->notice($user, 'adoption_completed', 'Adoção concluída!', $pet->name.' agora faz parte da sua família.', ['pet_id' => $pet->id, 'post_id' => $post->id]);
                }
            }
        }
    }

    private function post(User $user, ?Pet $pet, string $body, int $index, int $offset): ProfilePost
    {
        $source = $pet?->image_path ?? $this->images[$index % 2 ? 'cat' : 'dog'][$index % 4];
        $image = $this->copyImage($source, "demo/posts/{$user->id}-{$index}-{$offset}/cover");
        $gallery = $offset % 2 === 0 ? [$this->copyImage($pet?->gallery_paths[0] ?? $source, "demo/posts/{$user->id}-{$index}-{$offset}/gallery")] : [];

        return ProfilePost::create(['user_id' => $user->id, 'pet_id' => $pet?->id, 'body' => $body, 'image_path' => $image, 'gallery_paths' => $gallery, 'created_at' => now()->subDays(1 + $offset * 3)->subMinutes($index * 17)]);
    }

    private function createPosts(): void
    {
        $stories = ['Passeio de hoje com %s: muita curiosidade e uma pausa para descansar na sombra.', '%s descobriu um novo brinquedo favorito. A felicidade mora nas coisas simples!', 'Uma semana de pequenas conquistas com %s. Cada dia mais confiança e carinho.', 'Domingo de preguiça com %s. Quem mais tem um companheiro que ama uma soneca?'];
        foreach (array_slice($this->users, 0, 29) as $index => $user) {
            $owned = Pet::ownedBy($user->id)->get();
            for ($offset = 0; $offset < 4; $offset++) {
                $pet = $owned->isEmpty() ? null : $owned[$offset % $owned->count()];
                $body = $pet ? sprintf($stories[$offset], $pet->name) : ['Hoje visitei a sede e conheci histórias incríveis. Adotar transforma vidas!', 'Dica da comunidade: antes de receber um pet, prepare um cantinho tranquilo e seguro.', 'Compartilhando carinho por todos os animais que ainda esperam uma família.', 'Alguém tem dicas de passeios tranquilos e lugares que recebem pets?'][$offset];
                $this->post($user, $pet, $body, $index, $offset + 1);
            }
        }
        foreach (ProfilePost::all() as $index => $post) {
            foreach (['Que alegria ver vocês juntos!', 'Muito carinho por essa história. 🐾', 'Adorei! Depois conta como foi a adaptação.'] as $offset => $body) {
                $user = $this->users[($index + $offset + 2) % 29];
                $post->comments()->create(['user_id' => $user->id, 'body' => $body, 'created_at' => $post->created_at->copy()->addHours($offset + 1)]);
            }
            for ($offset = 1; $offset <= 5; $offset++) {
                ProfilePostLike::create(['profile_post_id' => $post->id, 'user_id' => $this->users[($index + $offset) % 29]->id]);
            }
            if ($index % 4 === 0) {
                $actor = $this->users[($index + 2) % 29];
                $this->notice(collect($this->users)->firstWhere('id', $post->user_id), 'post_comment', 'Novo comentário na sua publicação', $actor->name.' comentou na sua história.', ['sender_id' => $actor->id, 'post_id' => $post->id]);
            }
        }
    }

    private function createFriendships(): void
    {
        foreach (array_slice($this->users, 0, 29) as $index => $user) {
            for ($offset = 1; $offset <= 3; $offset++) {
                $friend = $this->users[($index + $offset) % 29];
                $first = min($user->id, $friend->id);
                $second = max($user->id, $friend->id);
                $request = FriendRequest::firstOrCreate(['sender_id' => $first, 'recipient_id' => $second], ['status' => 'accepted']);
                if (! $request->wasRecentlyCreated) {
                    continue;
                }
                $conversation = DirectConversation::create(['user_one_id' => $first, 'user_two_id' => $second]);
                foreach (['Oi! Vi sua publicação e adorei conhecer seus pets.', 'Obrigado! Eles deixam a casa muito mais alegre.', 'Você vai ao encontro na sede no fim de semana?', 'Vou sim! Podemos conversar sobre os passeios por lá.', 'Combinado! Até sábado 🐾'] as $messageIndex => $body) {
                    $message = DirectMessage::create(['direct_conversation_id' => $conversation->id, 'user_id' => $messageIndex % 2 || $messageIndex === 4 ? $second : $first, 'body' => $body, 'created_at' => now()->subHours(8)->addMinutes($index * 4 + $messageIndex)]);
                    $message->forceFill(['read_at' => $messageIndex < 4 ? $message->created_at->copy()->addMinute() : null])->save();
                    if ($messageIndex === 1) {
                        DirectMessageLike::create(['direct_message_id' => $message->id, 'user_id' => $first]);
                    }
                }
                $this->notice(collect($this->users)->firstWhere('id', $first), 'direct_message', 'Nova mensagem', 'Combinado! Até sábado 🐾', ['sender_id' => $second, 'conversation_id' => $conversation->id]);
            }
        }
        foreach ([8, 9, 10, 11] as $index) {
            $sender = $this->users[$index];
            $request = FriendRequest::create(['sender_id' => $sender->id, 'recipient_id' => $this->users[0]->id, 'status' => 'pending']);
            $this->notice($this->users[0], 'friend_request', 'Nova solicitação de amizade', $sender->name.' quer fazer parte da sua rede.', ['request_id' => $request->id, 'sender_id' => $sender->id]);
        }
        FriendRequest::create(['sender_id' => $this->users[12]->id, 'recipient_id' => $this->users[0]->id, 'status' => 'rejected']);
    }

    /** @param array<string, int> $data */
    private function notice(User $user, string $type, string $title, string $body, array $data): void
    {
        UserNotification::create(['user_id' => $user->id, 'type' => $type, 'title' => $title, 'body' => $body, 'data' => $data, 'read_at' => $user->id % 3 === 0 ? now()->subHour() : null]);
    }
}
